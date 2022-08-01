#!/bin/bash
DB_PASS=$(</dev/urandom tr -dc '12345!@#%qwertQWERTasdfgASDFGzxcvbZXCVB' | head -c25; echo "")
ID=$(</dev/urandom tr -dc '123456789abcdefghijklmnopqrstuvwxyz' | head -c5; echo "")
PORT=$(comm -23 <(seq 49152 65535 | sort) <(ss -Htan | awk '{print $4}' | cut -d':' -f2 | sort -u) | shuf | head -n 1)
WP_ADMIN_USER=$(</dev/urandom tr -dc '12345wertQWERTasdfgASDFGzxcvbZXCVB' | head -c10; echo "")
WP_ADMIN_PASS=$(</dev/urandom tr -dc '12345!@#%qwertQWERTasdfgASDFGzxcvbZXCVB' | head -c25; echo "")

mkdir -p instances

cat docker-compose.yml | sed "s/PASSWORD: Vpwytm9VW6necXiM1o1z/PASSWORD: \"$DB_PASS\"/g" > instances/$ID.yml

sed -i "s/- wordpress:/- wordpress_$ID/g" instances/$ID.yml
sed -i "s/- db:/- db_$ID:/g" instances/$ID.yml
sed -i "s/  wordpress:/  wordpress_$ID:/g" instances/$ID.yml
sed -i "s/  db:/  db_$ID:/g" instances/$ID.yml
sed -i "s/logs:/logs_$ID:/g" instances/$ID.yml
sed -i "s/WP Credentials/WP Credentials: localhost:$PORT $WP_ADMIN_USER $WP_ADMIN_PASS/g" instances/$ID.yml
sed -i "s/honeypress_wordpress/honeypress_wordpress_$ID/g" instances/$ID.yml
sed -i "s/honeypress_db/honeypress_db_$ID/g" instances/$ID.yml
sed -i "s/- 8080:80/- $PORT:80/g" instances/$ID.yml

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

./generators/corporatelorem.sh $ID 5
./generators/devlorem.sh $ID 5
./generators/theme.sh $ID
./generators/blogname.sh $ID

echo "Created instance $ID. Port $PORT. Credentials: $WP_ADMIN_USER and $WP_ADMIN_PASS"