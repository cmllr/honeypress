from wordpress:latest

ADD honeypress.json /var/www/html/honeypress.json
ADD .htaccess /var/www/html/.htaccess
ADD src/ /var/www/html/wp-content/plugins/honeypress
ADD logs/ /var/www/html/logs
USER root
RUN curl --output /wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
RUN chown -R www-data:www-data ./logs 