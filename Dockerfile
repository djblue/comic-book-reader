# Define parent image
FROM debian:wheezy

# Get nginx, php and friends
RUN apt-get update && \
    apt-get install -y g++ make curl nginx php5-dev php5-cli php5-fpm php5-sqlite php5-gd php-pear && \
    pecl install zip rar && \
    curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer && \
    apt-get autoremove -y g++ make curl php5-dev php-pear && apt-get clean -y

MAINTAINER djblue

# Install app dependencies
COPY composer.json /src/
RUN cd /src && composer install

EXPOSE 80

VOLUME /comics

RUN echo 'extension=rar.so\nextension=zip.so\n' >> /etc/php5/fpm/php.ini

# Bundle app source
COPY nginx.conf /etc/nginx/

COPY . /src

CMD service php5-fpm start && \
    service nginx start && \
    tail -f /var/log/nginx/access.log
