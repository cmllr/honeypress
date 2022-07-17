#/!bin/bash

CONTAINERS=$(docker ps  | grep -E "honeypress_wordpress"  | awk '{print $NF}' | sed "s/honeypress_wordpress_//g")

for container in $CONTAINERS
do
  ./report.sh $container > takeouts/$container.log
done

./cleanup.sh