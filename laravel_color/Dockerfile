FROM php:8.4-cli

ENV DEBIAN_FRONTEND=noninteractive

# Устанавливаем системные зависимости: PHP + Node.js
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        git \
        curl \
        libfreetype-dev \
        libjpeg-dev \
        libpng-dev \
        libzip-dev \
        libonig-dev \
        zip \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) gd zip mbstring pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Устанавливаем Node.js 20 (LTS)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs && \
    npm install -g npm@latest

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Проект
WORKDIR /app
COPY . .

# Устанавливаем PHP-зависимости
RUN composer install --optimize-autoloader --no-dev --prefer-dist

# Устанавливаем и собираем фронтенд (React через Inertia)
RUN npm install && \
    npm run build

# Генерация APP_KEY
RUN cp .env.example .env || true && \
    php artisan key:generate

# Порт
EXPOSE 8000

# Запуск
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
