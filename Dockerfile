FROM php:8.2-cli

# Install MySQL extension
RUN docker-php-ext-install mysqli

WORKDIR /app

COPY . /app

EXPOSE 10000

CMD ["php", "-S", "0.0.0.0:10000"]
