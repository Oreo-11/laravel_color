# Используем PHP 8.4 CLI (официальный образ существует!)
FROM php:8.4-cli

ENV DEBIAN_FRONTEND=noninteractive

# Устанавливаем ВСЕ зависимости: JPEG, PNG, oniguruma (для mbstring)
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
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

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Проект
WORKDIR /app
COPY . .

# Устанавливаем зависимости БЕЗ проверки платформы (чтобы избежать ошибок)
RUN composer install --optimize-autoloader --no-dev --ignore-platform-reqs

# Генерация ключа
RUN cp .env.example .env || true && php artisan key:generate

# Порт
EXPOSE 8000

# Запуск
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
