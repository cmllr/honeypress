#!/bin/bash
DB_PASS=$(openssl rand -base64 25 | md5 | head -c25;echo|xargs)
ID=$(openssl rand -base64 5 | md5 | head -c5;echo|xargs)
PLATFORM=$(uname)
ARCH=$(arch)
SED_COMMAND="sed"
IMAGE="honeypress:latest"
DOCKERFILE_PATH="Dockerfile"
REBUILD_IMAGE=false
PUBLIC_URL="http://localhost"
LABELS=""

function show_help() {
    cat branding.txt
    echo "deploy.sh -b: Force a rebuild of the selected image, even when the image already exists."
    echo "deploy.sh -d <path>: Change the dockerfile path. Context will be still '.'. Default is 'Dockerfile'."
    echo "deploy.sh -u <url>: Set the public URL (homeurl/siteurl). The default is 'localhost:port'. If a value is provided, the port flag (-p and it's defualt) will be ignored."
    echo "deploy.sh -i <name:tag>: Change the used image name and tag. Default is 'honeypress:latest'"
    echo "deploy.sh -l label1=value1,label2=value2: Add labels to the honeypress container. Use ',' to separate labels, avoid separator in labels. Defaults to nothing."
    echo "deploy.sh -p <number>: Change the port of the newly spawned instance"
    echo "deploy.sh -h: Show this message"
    exit 0
}
OPTIONS_PRESENT=false
while getopts "d:i:l:p:u:hb" flag
do
    OPTIONS_PRESENT=true
    case "${flag}" in
        b) REBUILD_IMAGE=true;;
        d) DOCKERFILE_PATH=${OPTARG};;
        h) show_help;;
        i) IMAGE=${OPTARG};;
        l) LABELS=${OPTARG};;
        p) PORT=${OPTARG};;
        u) PUBLIC_URL="${OPTARG}";;
    esac
done

# Show the help text if nothing was provided
if [[ $OPTIONS_PRESENT = false ]]; then
    show_help
fi

if [[ $PLATFORM == *"Darwin"* ]]; then
    SED_COMMAND="gsed"
fi

if [[ -z $PORT ]]; then
    if [[ $PLATFORM == *"Darwin"* ]]; then
        echo "Auto port choosing is not supported on mac. See https://github.com/cmllr/honeypress/issues/5 for details"
        exit 1
    else
        PORT=$(comm -23 <(seq 49152 65535 | sort) <(ss -Htan | awk '{print $4}' | cut -d':' -f2 | sort -u) | shuf | head -n 1)
    fi
fi

# Check if the docker daemon is running at all.
DOCKER_RUNNING=$(docker ps > /dev/null 2>&1)
if [[ $? -ne 0 ]];
then
  echo "Is your Docker service running? Could not connect to the service."
  exit 1
fi

compose=$(env docker-compose > /dev/null 2>&1)
if [[ $? -ne 0 ]];
then
  echo "The docker-compose command was not found."
  exit 1
fi

# Check if the docker image exists, if not build $IMAGE from $DOCKERFILE_PATH
if [[ "$(docker images -q $IMAGE 2> /dev/null)" == "" ]]; then
  # do something
  echo "The image $IMAGE is not present in the image list. Creating the image..."
  docker build . -f $DOCKERFILE_PATH -t $IMAGE
fi

if [[ "$REBUILD_IMAGE" = true ]]; then
  # do something
  echo "A rebuild for the image was requested."
  docker build . -f $DOCKERFILE_PATH -t $IMAGE
fi


WP_ADMIN_USER=$(openssl rand -base64 10 | md5 | head -c10;echo|xargs)
WP_ADMIN_PASS=$(openssl rand -base64 10 | md5 | head -c10;echo|xargs)

mkdir -p instances

cat docker-compose.yml | $SED_COMMAND "s/PASSWORD: Vpwytm9VW6necXiM1o1z/PASSWORD: \"$DB_PASS\"/g" > instances/$ID.yml

if [ -z "$LABELS" ]
then
    $SED_COMMAND -i "s/.*LABELS//g" instances/$ID.yml
else
    echo "Adding labels to the container..."
    $SED_COMMAND -i "s/LABELS/labels:\n      - $LABELS/g" instances/$ID.yml
    $SED_COMMAND -i "s/,/\n      - /g" instances/$ID.yml
fi
$SED_COMMAND -i "s/IMAGE/$IMAGE/g" instances/$ID.yml
$SED_COMMAND -i "s/- wordpress:/- wordpress_$ID/g" instances/$ID.yml
$SED_COMMAND -i "s/- db:/- db_$ID:/g" instances/$ID.yml
$SED_COMMAND -i "s/  wordpress:/  wordpress_$ID:/g" instances/$ID.yml
$SED_COMMAND -i "s/  db:/  db_$ID:/g" instances/$ID.yml
$SED_COMMAND -i "s/logs:/logs_$ID:/g" instances/$ID.yml
$SED_COMMAND -i "s/WP Credentials/WP Credentials: localhost:$PORT $WP_ADMIN_USER $WP_ADMIN_PASS/g" instances/$ID.yml
$SED_COMMAND -i "s/honeypress_wordpress/honeypress_wordpress_$ID/g" instances/$ID.yml
$SED_COMMAND -i "s/honeypress_db/honeypress_db_$ID/g" instances/$ID.yml
$SED_COMMAND -i "s/- 8080:80/- $PORT:80/g" instances/$ID.yml

if [[ $ARCH == *"arm64"* ]]; then
    $SED_COMMAND -i "s/mysql:5.7/arm64v8\/mysql:latest/g" instances/$ID.yml
fi
docker-compose -f instances/$ID.yml up -d

sleep 10

# complete WP setup
docker exec "honeypress_wordpress_$ID" bash -c "php /wp-cli.phar --allow-root core install --title='My Site' --admin_user=$WP_ADMIN_USER --admin_password=$WP_ADMIN_PASS --admin_email=exampleAdmin@nowhere.org --url=http://localhost:$PORT"

if [[ $PUBLIC_URL == "http://localhost" ]]; then
    echo "We will use $PUBLIC_URL:$PORT as the siteurl and home."
    docker exec "honeypress_wordpress_$ID" bash -c "php /wp-cli.phar --allow-root option set siteurl $PUBLIC_URL:$PORT"
    docker exec "honeypress_wordpress_$ID" bash -c "php /wp-cli.phar --allow-root option set home $PUBLIC_URL:$PORT"
else
    echo "We will use $PUBLIC_URL as the siteurl and home."
    docker exec "honeypress_wordpress_$ID" bash -c "php /wp-cli.phar --allow-root option set siteurl $PUBLIC_URL"
    docker exec "honeypress_wordpress_$ID" bash -c "php /wp-cli.phar --allow-root option set home $PUBLIC_URL"
fi
docker exec "honeypress_wordpress_$ID" bash -c "php /wp-cli.phar --allow-root plugin activate honeypress"
docker exec "honeypress_wordpress_$ID" bash -c "php /wp-cli.phar --allow-root post delete 1"
# fix permissions
docker exec "honeypress_wordpress_$ID" bash -c "chown -R www-data:www-data ./logs"
docker exec "honeypress_wordpress_$ID" bash -c "chown -R www-data:www-data ./wp-content"
docker exec "honeypress_wordpress_$ID" bash -c "sed -i 's/\"admin\"/\"$WP_ADMIN_USER\"/g' ./honeypress.json" # make sure the admin is not deleted

echo "Adding content, please wait"

# Add blog theme and names
./generators/theme.sh $ID
./generators/blogname.sh $ID

docker exec "honeypress_wordpress_$ID" bash -c "mkdir /tmp/init"
docker cp ./generators/corporatelorem.sh "honeypress_wordpress_$ID:/tmp/init/corporatelorem.sh"
docker cp ./generators/devlorem.sh "honeypress_wordpress_$ID:/tmp/init/devlorem.sh"
docker exec "honeypress_wordpress_$ID" bash -c "/tmp/init/corporatelorem.sh 5"
docker exec "honeypress_wordpress_$ID" bash -c "/tmp/init/devlorem.sh 5"
docker exec "honeypress_wordpress_$ID" bash -c "rm -rf /var/www/html/logs/*"

echo "Created instance $ID. Port $PORT. Credentials: $WP_ADMIN_USER and $WP_ADMIN_PASS"