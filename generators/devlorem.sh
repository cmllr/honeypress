#!/bin/bash
docker exec "honeypress_wordpress_$1" bash -c " php /wp-cli.phar --allow-root term create category people --description='people'"
COUNT=$2
for i in $( seq 1 $COUNT )
do
  echo "Generating post #$i"
  CONTENT=$(curl https://devlorem.kovah.de/api/2?format=text&paragraphs=false)
  TITLE=$(echo $CONTENT | grep -Eo "^[^<]+" | xargs)
  BODY=$(echo $CONTENT |  sed "s/^$TITLE //g" | sed -e 's/<[^>]*>/\n/g' | sed "s/\"/'/g"  | sed "s/\’/'/g")
  DATE=$(shuf -n1 -i$(date -d '2005-01-01' '+%s')-$(date -d '2022-01-01' '+%s') | xargs -I{} date -d '@{}' '+%Y-%m-%d %H:%M:%S')
  docker exec "honeypress_wordpress_$1" bash -c "php /wp-cli.phar --allow-root post create --post_content=\"$BODY\" --post_category='people' --post_status='publish' --post_date='$DATE' --post_title='$TITLE' --post_author=1"
  sleep 1
done