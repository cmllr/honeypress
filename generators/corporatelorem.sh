#!/bin/bash
php /wp-cli.phar --allow-root term create category business --description='business'

COUNT=$1
for i in $( seq 1 $COUNT )
do
  echo "Generating post #$i"
  CONTENT=$(curl https://corporatelorem.kovah.de/api/2?format=text&paragraphs=false)
  TITLE=$(echo $CONTENT | grep -Eo "^[^<]+" | xargs)
  BODY=$(echo $CONTENT |  sed "s/^$TITLE //g" | sed -e 's/<[^>]*>/\n/g' | sed "s/\"//g"  | sed "s/\’//g" | sed "s/\'//g")
  DATE=$(shuf -n1 -i$(date -d '2005-01-01' '+%s')-$(date -d '2022-01-01' '+%s') | xargs -I{} date -d '@{}' '+%Y-%m-%d %H:%M:%S')
  php /wp-cli.phar --allow-root post create --post_content="$BODY" --post_category="business" --post_status="publish" --post_date="$DATE" --post_title="$TITLE" --post_author=1
  sleep 1
done