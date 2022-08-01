#!/bin/bash

SESSIONS=$(docker exec -it "honeypress_wordpress_$1" bash -c "find logs -maxdepth 1 -type d | tail -n +2 | sed s/logs//g | sed s/^.//g | sed s/\$//g")

for session in $SESSIONS
do
    echo "Session: $session"
    RAW_PATH=$(echo "logs/$session"|sed 's/.$//') # newline removals
    REQUEST_FILES=$(docker exec -it "honeypress_wordpress_$1" bash -c "ls -t '$RAW_PATH' | sort")


    for request in $REQUEST_FILES
    do
      REQUEST_TIME=$(echo $request | sed 's/[^0-9]*//g' | sed 's/.$//g')
      if [ ! -z "$REQUEST_TIME" ]
      then
        REQUEST_TIME_SECONDS=$(expr $REQUEST_TIME / 1000)
        DATE_TIME=$(date +'%Y-%m-%d %H:%M:%S' -d "@$REQUEST_TIME_SECONDS")
        echo "Datetime: $DATE_TIME" 
        echo "Logfile: $request"
        if [[ $request =~ "request" ]]; then
          REQUEST_LOGFILE_PATH=$(echo $RAW_PATH/$request | sed 's/.$//g' )
          TARGET=$(docker exec -it "honeypress_wordpress_$1" cat  $REQUEST_LOGFILE_PATH | grep -o -E "target\":\"[^\"]*" | sed "s/target\":\"//g")
          if [ ! -z $TARGET ]
          then
            echo "Target: $TARGET"
            IP=$(docker exec -it "honeypress_wordpress_$1" cat  $REQUEST_LOGFILE_PATH | grep -o -E "ip\":\"[^\"]*" | sed "s/ip\":\"//g")
            echo "Client IP: $IP"
          fi
        fi

        if [[ $request =~ "fileupload" ]]; then
          FILE_UPLOAD_LOGFILE_PATH=$(echo $RAW_PATH/$request | sed 's/.$//g' )
          FILE=$(docker exec -it "honeypress_wordpress_$1" cat $FILE_UPLOAD_LOGFILE_PATH|grep -oe "file\":.*}}" | sed s/file\"://g)
          if [ ! -z $FILE ]
          then
            echo "Fileupload: $FILE"
            FILE_HASH=$(echo $FILE|grep -oe "hash\":.*}}" | sed s/hash\":\"//g | sed s/\"}}//g)
            mkdir -p ./takeouts/$1/
            docker cp honeypress_wordpress_$1:/var/www/html/$RAW_PATH/uploads/$FILE_HASH ./takeouts/$1/$FILE_HASH
          fi
        fi

        if [[ $request =~ "filedropnew" ]]; then
          FILE_DROP_LOGFILE_PATH=$(echo $request | sed 's/.$//g')
          FILE_DROP_NAME=$(docker exec -it "honeypress_wordpress_$1" cat  $RAW_PATH/$FILE_DROP_LOGFILE_PATH)
          echo "File dropped: $FILE_DROP_NAME"
        fi

        if [[ $request =~ "delete" ]]; then
          FILE_DROP_LOGFILE_PATH=$(echo $request | sed 's/.$//g')
          FILE_DROP_NAME=$(docker exec -it "honeypress_wordpress_$1" cat  $RAW_PATH/$FILE_DROP_LOGFILE_PATH)
          echo "File dropped: $FILE_DROP_NAME"
        fi



        if [[ $request =~ "dashboard" ]]; then
          DASHBOARD_LOGFILE_PATH=$(echo $request | sed 's/.$//g')
          TARGET_DASHBOARD_PAGE=$(docker exec -it "honeypress_wordpress_$1" cat  $RAW_PATH/$DASHBOARD_LOGFILE_PATH)
          echo "Page on dashboard opened: $TARGET_DASHBOARD_PAGE"
        fi
      fi

      if [[ $request =~ "credentials.json" ]]; then
        CREDENTIALS_LOGFILE_PATH=$(echo $request | sed 's/.$//g')
        CREDENTIALS_USED=$(docker exec -it "honeypress_wordpress_$1" cat  $RAW_PATH/$CREDENTIALS_LOGFILE_PATH|grep -oe "credentials\":.*}}" | sed s/credentials\"://)
        echo "Credentials used for login: $CREDENTIALS_USED (Session will most likely renewed now)"
      fi

    done

done