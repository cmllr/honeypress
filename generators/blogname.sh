#!/bin/bash
NAME=$(shuf -n 1 ./generators/names.txt)
TAGLINE=$(shuf -n 1 ./generators/taglines.txt)

BLOG_NAME="$(echo $NAME)s blog"

docker exec "honeypress_wordpress_$1" bash -c "php /wp-cli.phar --allow-root option update blogname '$BLOG_NAME'"
docker exec "honeypress_wordpress_$1" bash -c "php /wp-cli.phar --allow-root option update blogdescription '$TAGLINE'"
docker exec "honeypress_wordpress_$1" bash -c "php /wp-cli.phar --allow-root user update 1 --display_name="$NAME""