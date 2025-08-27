# ClaimIt - Apache Deployment Guide

## 📋 Prerequisites
- Apache web server with mod_rewrite enabled
- PHP 8.0+ 
- Composer installed
- AWS S3 bucket configured

## 🚀 Deployment Steps

### 1. Upload Files
Upload the entire project to your server:
```bash
/var/www/claimit.stonekeep.com/
├── public/           ← Document root points here
├── config/
├── templates/
├── src/
├── assets/
├── vendor/
└── composer.json
```

### 2. Apache Virtual Host Configuration
```apache
<VirtualHost *:80>
    ServerName claimit.stonekeep.com
    DocumentRoot /var/www/claimit.stonekeep.com/public
    
    <Directory /var/www/claimit.stonekeep.com/public>
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>
    
    # Optional: Log files
    ErrorLog ${APACHE_LOG_DIR}/claimit_error.log
    CustomLog ${APACHE_LOG_DIR}/claimit_access.log combined
</VirtualHost>
```

### 3. Configure AWS Credentials
Copy and configure your AWS credentials:
```bash
cp config/aws-credentials.example.php config/aws-credentials.php
```

Edit `config/aws-credentials.php` with your actual AWS credentials.

### 4. Set Permissions
```bash
# Make sure Apache can read files
chown -R www-data:www-data /var/www/claimit.stonekeep.com/
chmod -R 755 /var/www/claimit.stonekeep.com/

# Secure config directory (outside document root)
chmod 600 /var/www/claimit.stonekeep.com/config/aws-credentials.php
```

### 5. Install Dependencies
```bash
cd /var/www/claimit.stonekeep.com/
composer install --no-dev --optimize-autoloader
```

### 6. Enable Apache Modules
```bash
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod expires
sudo systemctl restart apache2
```

## 🌐 URL Structure

### With .htaccess (Clean URLs):
- `https://claimit.stonekeep.com/` → Home
- `https://claimit.stonekeep.com/claim` → Post Item  
- `https://claimit.stonekeep.com/s3` → View Items

### Without .htaccess (Query Parameters):
- `https://claimit.stonekeep.com/` → Home
- `https://claimit.stonekeep.com/?page=claim` → Post Item
- `https://claimit.stonekeep.com/?page=s3` → View Items

## 🔒 Security Considerations

### SSL Certificate (Recommended)
```apache
<VirtualHost *:443>
    ServerName claimit.stonekeep.com
    DocumentRoot /var/www/claimit.stonekeep.com/public
    
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    
    # Same directory configuration as above...
</VirtualHost>
```

### Hide Sensitive Files
The `.htaccess` file already includes security headers, but ensure:
- `config/` directory is outside document root ✅
- `vendor/` directory is outside document root ✅  
- `src/` directory is outside document root ✅

## 📊 Error Logs
Errors will be logged to:
- Apache error log: `/var/log/apache2/claimit_error.log`
- PHP errors: Apache error log (configured in `index.php`)

## 🧪 Testing
1. Visit your domain to test the home page
2. Navigate to posting and viewing pages
3. Test file uploads and S3 integration
4. Verify delete functionality works

## 🔧 Troubleshooting

### Assets not loading?
- Check that `assets/` directory path is correct in `.htaccess`
- Verify Apache has read permissions

### AWS errors?
- Verify `aws-credentials.php` configuration
- Check S3 bucket permissions
- Review Apache error logs

### Clean URLs not working?
- Ensure `mod_rewrite` is enabled
- Check `.htaccess` file permissions
- Verify `AllowOverride All` in virtual host 