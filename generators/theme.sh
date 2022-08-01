#!/bin/bash
THEME=$(shuf -n 1 ./generators/themes.txt)

docker exec "honeypress_wordpress_$1" bash -c "php /wp-cli.phar --allow-root theme install $THEME --activate"