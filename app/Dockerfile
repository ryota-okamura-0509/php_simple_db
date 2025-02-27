FROM php:8.2-apache

# 必要なパッケージをインストール
RUN apt-get update && \
    apt-get install -y zip unzip git libzip-dev && \
    docker-php-ext-configure zip && \
    docker-php-ext-install zip

# 作業ディレクトリを設定
WORKDIR /var/www/html

# プロジェクトファイルをコピー
COPY .  /var/www/html/

# composerのインストール
COPY --from=composer:2.6.6 /usr/bin/composer /usr/bin/composer

# Composerの依存関係をインストール
RUN composer install

# phpunitのエイリアスを設定
RUN echo 'alias phpunit="vendor/bin/phpunit"' >> ~/.bashrc
