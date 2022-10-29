from wordpress:latest

ADD honeypress.json /var/www/html/honeypress.json
ADD .htaccess /var/www/html/.htaccess
ADD src/ /var/www/html/wp-content/plugins/honeypress
ADD logs/ /var/www/html/logs

USER root
RUN curl --output /wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar

RUN touch ./logs/global.log

RUN chown -R www-data:www-data ./logs 

# Supress the default WordPress output and put the HoneyPress ouput in the foreground
RUN sed -i "s/echo >&2/echo \>\/dev\/null/g" /usr/local/bin/docker-entrypoint.sh \ 
    && cat /usr/local/bin/apache2-foreground   | sed "s/exec apache2 \-DFOREGROUND \"\$\@\"/apache2 \-DFOREGROUND \"\$\@\" \> \/dev\/null 2>\&1 \&/g" > /usr/local/bin/apache2-honeypress \
    && echo "tail -f /var/www/html/logs/global.log" >> /usr/local/bin/apache2-honeypress \
    && chmod +x /usr/local/bin/apache2-honeypress

CMD ["apache2-honeypress"]