FROM laravelphp/vapor:php84

COPY docker/uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini
COPY . /var/task
