FROM php:8.2-apache

# Enable mod_rewrite for Apache
RUN a2enmod rewrite
# Enable necessary Apache modules
RUN a2enmod rewrite ssl


# Install curl
RUN apt-get update && apt-get install -y libcurl4 curl openssl && apt-get clean

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Generate self-signed SSL certificate valid for 10 years
RUN mkdir -p /etc/ssl/mycerts && \
    openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
    -keyout /etc/ssl/mycerts/selfsigned.key \
    -out /etc/ssl/mycerts/selfsigned.crt \
    -subj "/C=US/ST=State/L=City/O=Organization/OU=Unit/CN=localhost"

# Configure Apache for HTTPS
RUN echo "<IfModule mod_ssl.c>\n\
<VirtualHost *:443>\n\
    ServerAdmin admin@example.com\n\
    ServerName localhost\n\
    DocumentRoot /var/www/html\n\
    SSLEngine on\n\
    SSLCertificateFile /etc/ssl/mycerts/selfsigned.crt\n\
    SSLCertificateKeyFile /etc/ssl/mycerts/selfsigned.key\n\
    <Directory /var/www/html>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog \${APACHE_LOG_DIR}/error.log\n\
    CustomLog \${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>\n\
</IfModule>" > /etc/apache2/sites-available/default-ssl.conf && \
    a2ensite default-ssl.conf



# Expose port 80
EXPOSE 80 443
