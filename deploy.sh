#!/bin/bash
DB_PASS=$(openssl rand -base64 25 | md5 | head -c25;echo|xargs)
ID=$(openssl rand -base64 5 | md5 | head -c5;echo|xargs)
PLATFORM=$(uname)
ARCH=$(arch)
SED_COMMAND="sed"
IMAGE="honeypress:latest"
DOCKERFILE_PATH="Dockerfile"
while getopts p: flag
do
    case "${flag}" in
        p) PORT=${OPTARG};;
    esac
done

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

# Check if the docker image exists, if not build $IMAGE from $DOCKERFILE_PATH
if [[ "$(docker images -q $IMAGE 2> /dev/null)" == "" ]]; then
  # do something
  echo "The image $IMAGE is not present in the image list. Creating the image..."
  docker build . -f $DOCKERFILE_PATH -t $IMAGE
fi

WP_ADMIN_USER=$(openssl rand -base64 10 | md5 | head -c10;echo|xargs)
WP_ADMIN_PASS=$(openssl rand -base64 10 | md5 | head -c10;echo|xargs)

mkdir -p instances

cat docker-compose.yml | sed "s/PASSWORD: Vpwytm9VW6necXiM1o1z/PASSWORD: \"$DB_PASS\"/g" > instances/$ID.yml

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
docker exec "honeypress_wordpress_$ID" bash -c "php /wp-cli.phar --allow-root option set siteurl http://localhost:$PORT"
docker exec "honeypress_wordpress_$ID" bash -c "php /wp-cli.phar --allow-root option set home http://localhost:$PORT"
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