# -*- mode: ruby -*-
# # vi: set ft=ruby :

# Uncomment or set to specific provider if you experience some provider issues
#ENV['VAGRANT_DEFAULT_PROVIDER'] = ''

Vagrant.configure("2") do |config|
  config.vm.provider "libvirt"
  config.vm.provider "virtualbox"
  config.vm.box = "fedora/23-cloud-base"

  config.vm.network "forwarded_port", guest: 80, host: 8080, auto_correct: true
  config.vm.synced_folder ".", "/vagrant", id: "vagrant", :nfs => true, :mount_options => ['nolock,vers=3,udp']

  config.vm.provision "shell",
      inline: <<'SHELLSCRIPT'
PHP_IMAGE="speckcommerce/php:7.0"

dnf install -y git docker nginx
systemctl start docker.service
systemctl enable docker.service

# composer image is built locally to ensure it uses correct php image
docker build -t localhost/composer - << DOCKERFILE
FROM ${PHP_IMAGE}
RUN curl -sS https://getcomposer.org/installer \
    | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer --version

WORKDIR /app
CMD ["-"]
ENTRYPOINT ["composer", "--ansi"]
DOCKERFILE
# does not work as expected as of now, @see https://github.com/docker/docker/issues/17907
# volume is deleted on docker run --rm atm
docker volume create --name=composer-cache
# hack to make named volume persist on --rm
docker run --name=composer-cache -v composer-cache:/root/.composer/cache --entrypoint /bin/true localhost/composer

cat > /usr/local/bin/d-composer << EOL
#!/bin/bash
sudo docker run --rm -v composer-cache:/root/.composer/cache -v "\$(pwd)":/app \
    localhost/composer "\$@"
EOL
chmod +x /usr/local/bin/d-composer

cat > /usr/local/bin/d-php << EOL
#!/bin/bash
sudo docker run --rm -v "\$(pwd)":/app -t\$([ -t 0 ] && echo i) -w=/app ${PHP_IMAGE} "\$@"
EOL
chmod +x /usr/local/bin/d-php

# lazy initial setup for nginx + php-fpm just to get going
setenforce 0
sed -i s/SELINUX=enforcing/SELINUX=disabled/g /etc/selinux/config

cat > /etc/nginx/nginx.conf <<"EOL"
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log;
pid /run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    log_format  main  '$remote_addr - $remote_user [$time_local] "$request" '
                      '$status $body_bytes_sent "$http_referer" '
                      '"$http_user_agent" "$http_x_forwarded_for"';

    access_log  /var/log/nginx/access.log  main;

    sendfile            on;
    tcp_nopush          on;
    tcp_nodelay         on;
    keepalive_timeout   65;
    types_hash_max_size 2048;

    include             /etc/nginx/mime.types;
    default_type        application/octet-stream;

    # Load modular configuration files from the /etc/nginx/conf.d directory.
    # See http://nginx.org/en/docs/ngx_core_module.html#include
    # for more information.
    include /etc/nginx/conf.d/*.conf;

    server {
        listen 80 default;
        server_name _;
        root /vagrant/public;

        index index.php index.html;

        location / {
            try_files $uri $uri/ /index.php?$args;
        }

        location ~ \.php$ {
            try_files $uri /index.php?$args;
            expires off;
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            fastcgi_param PATH_INFO $fastcgi_script_name;
            include fastcgi_params;
        }

        location ~ ^/\. {
            deny all;
        }
    }
}
EOL
systemctl start nginx.service
systemctl enable nginx.service
docker run -d --name=php-fpm --restart=always -v /vagrant:/vagrant -p 127.0.0.1:9000:9000 ${PHP_IMAGE}-fpm

# and some nice
echo 'd-composer update' >> /home/vagrant/.bash_history
echo 'd-php vendor/bin/phpcbf' >> /home/vagrant/.bash_history
echo 'd-php vendor/bin/phpcs' >> /home/vagrant/.bash_history
echo 'd-php vendor/bin/phpunit' >> /home/vagrant/.bash_history
SHELLSCRIPT

end

