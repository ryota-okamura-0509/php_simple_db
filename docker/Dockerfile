FROM php:8.2-apache

# 必要なパッケージをインストール
# 必要なパッケージをインストール
RUN apt-get update && \
    apt-get install -y zip unzip git && \
    docker-php-ext-install zip

# 作業ディレクトリを設定
WORKDIR /var/www/html

COPY ../  /var/www/html/

# composerのインストール
COPY --from=composer:2.6.6 /usr/bin/composer /usr/bin/composer
# Composerの依存関係をインストール
RUN composer install
# サービスのエントリーポイントを設定
CMD ["php", "app/index.php"]