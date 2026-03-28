FROM php:8.2-cli

# Install dependencies for PostgreSQL and MySQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install mysqli pgsql pdo_pgsql

# Set the working directory
WORKDIR /app

# Copy all files from your repo to the container
COPY . /app

# Render uses port 10000 by default for some plans, 
# but PHP's built-in server needs to listen on it
EXPOSE 10000

# Start the PHP built-in server
CMD ["php", "-S", "0.0.0.0:10000"]
