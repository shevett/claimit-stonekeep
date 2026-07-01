# ClaimIt

A modern PHP web application for sharing and claiming free items and deals in your community. Post items you want to give away or sell, browse what others have listed, and claim items you're interested in.

**Live Site**: [claimit.cc](https://claimit.cc)

### License

ClaimIt is licensed under CC BY-NC-SA 4.0.

The "ClaimIt" name and associated branding are trademarks of Dave Shevett.
Forks and derivative works must use a different name and branding to avoid confusion with the original project.

### Commercial Licensing

For commercial licensing, hosted deployments, or other commercial use,
please contact Dave Shevett.

Email: shevett@pobox.com
Website: https://claimit.cc/

## 🚀 Features

### Core Functionality
- **Item Listings**: Post items for free or for sale with photos and descriptions
- **Claiming System**: Users can claim items or join a waitlist
- **Image Management**: Upload multiple images per item with rotation and deletion
- **Gone/Relist**: Mark items as gone when claimed, or relist them if they become available
- **Search**: Real-time search across all item fields
- **User Profiles**: View all items posted by a specific user

### Authentication & Users
- **Google OAuth Login**: Secure authentication via Google accounts
- **User Settings**: Customize display name, zipcode, notification preferences
- **Show/Hide Gone Items**: User preference to filter out claimed items
- **User Dashboard**: Manage your posted items and claimed items

### Technical Features
- **AWS S3 Storage**: All images stored in S3 with CloudFront CDN delivery
- **MySQL Database**: Items and users stored in database with Phinx migrations
- **Email Notifications**: AWS SES integration for notifications
- **Responsive Design**: Works on desktop, tablet, and mobile
- **Open Graph Tags**: Beautiful link previews for social media sharing
- **Performance Optimized**: Efficient queries, caching, lazy loading

### Admin Features
- **Admin Panel**: Database and email testing tools
- **Item Management**: Edit or delete any item
- **User Management**: View and manage all users

## 📁 Project Structure

```
claimit.stonekeep.com/
├── public/
│   ├── index.php              # Main entry point & routing
│   └── assets/
│       ├── css/style.css      # Main stylesheet
│       ├── js/app.js          # JavaScript functionality
│       └── images/            # Static images (logo, etc.)
├── src/
│   ├── AuthService.php        # Google OAuth authentication
│   ├── AwsService.php         # AWS S3/SES/CloudFront integration
│   └── EmailService.php       # Email notifications
├── config/
│   ├── config.php             # Main configuration
│   ├── aws-credentials.php    # AWS credentials
│   ├── google-oauth.php       # Google OAuth credentials
│   └── smtp-config.php        # Email configuration
├── includes/
│   └── functions.php          # Core utility functions
├── templates/
│   ├── home.php               # Homepage with item grid
│   ├── items.php              # Browse all items
│   ├── item.php               # Individual item detail
│   ├── claim.php              # Post new item
│   ├── user-listings.php      # User's posted items
│   ├── dashboard.php          # User dashboard
│   ├── settings.php           # User settings
│   ├── admin.php              # Admin panel
│   ├── login.php              # Login page
│   ├── about.php              # About page
│   ├── contact.php            # Contact page
│   ├── changelog.php          # Changelog display
│   └── item-card.php          # Reusable item card component
├── db/
│   ├── migrations/            # Phinx database migrations
│   └── seeds/                 # Database seeders
├── scripts/
│   ├── migrate_users_to_db.php    # User migration script
│   ├── migrate_items_to_db.php    # Item migration script
│   └── generate-changelog.php     # Generate changelog from commits
├── composer.json              # Composer dependencies
├── phinx.php                  # Phinx migration configuration
└── LICENSE                    # CC BY-NC-SA 4.0 license
```

## 🛠️ Requirements

- **PHP**: 8.0 or higher
- **MySQL**: 5.7 or higher (or MariaDB 10.2+)
- **Web Server**: Apache or Nginx
- **Composer**: For dependency management
- **AWS Account**: For S3 storage and SES email (optional for local dev)
- **Google OAuth**: For authentication

### PHP Extensions Required
- PDO with MySQL driver
- GD or Imagick (for image processing)
- cURL
- OpenSSL
- mbstring

## 📥 Installation

### 1. Clone the Repository
```bash
git clone https://github.com/shevett/claimit-stonekeep.git
cd claimit-stonekeep
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Configure Database
Create a MySQL database and update `config/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'claimit_dev');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
```

### 4. Run Migrations
```bash
vendor/bin/phinx migrate -e development
```

### 5. Configure AWS (Optional for Development)
Copy example files and add your credentials:
```bash
cp config/aws-credentials.example.php config/aws-credentials.php
# Edit config/aws-credentials.php with your AWS keys
```

See [AWS_SETUP.md](AWS_SETUP.md) for detailed AWS configuration.

### 6. Configure Google OAuth
Copy example file and add your OAuth credentials:
```bash
cp config/google-oauth.example.php config/google-oauth.php
# Edit config/google-oauth.php with your Client ID and Secret
```

See [GOOGLE_OAUTH_SETUP.md](GOOGLE_OAUTH_SETUP.md) for detailed OAuth setup.

### 7. Start Development Server
```bash
cd public
php -S localhost:8000
```

Visit `http://localhost:8000` in your browser.

## ⚙️ Configuration

### Environment Mode
Set in `config/config.php`:
```php
define('DEVELOPMENT_MODE', true);  // Set to false for production
```

### Admin Users
Add admin user IDs in `config/config.php`:
```php
define('ADMIN_USERS', ['your_google_user_id']);
```

### AWS Services
- **S3 Bucket**: Configure in `config/aws-credentials.php`
- **CloudFront**: Set distribution URL for image CDN
- **SES**: Configure for email notifications

## 🗄️ Database

### Migrations
Create a new migration:
```bash
vendor/bin/phinx create MigrationName
```

Run migrations:
```bash
vendor/bin/phinx migrate -e production
```

Check migration status:
```bash
vendor/bin/phinx status -e production
```

### Tables
- **users**: User accounts and settings
- **items**: Item listings with metadata
- **claims**: Item claims and waitlist
- **phinxlog**: Migration history

## 🚀 Deployment

### Production Deployment
See [DEPLOYMENT.md](DEPLOYMENT.md) for detailed production deployment instructions.

Quick checklist:
1. Set `DEVELOPMENT_MODE` to `false`
2. Configure production database
3. Set up SSL/HTTPS
4. Configure AWS S3 and CloudFront
5. Set up Google OAuth with production callback URL
6. Configure Apache/Nginx with proper document root
7. Run database migrations
8. Set proper file permissions

### Apache Configuration
Point document root to `public/` directory:
```apache
DocumentRoot /var/www/claimit.stonekeep.com/public
<Directory /var/www/claimit.stonekeep.com/public>
    AllowOverride All
    Require all granted
</Directory>
```

## 🎯 Usage

### For Users
1. **Login**: Click login and authenticate with Google
2. **Browse Items**: View all available items on the home page
3. **Search**: Use the search bar to find specific items
4. **Claim Items**: Click "Claim This!" to add yourself to the list
5. **Post Items**: Click "Make a new posting" to list your items
6. **Manage**: View your listings and claimed items in your dashboard

### For Admins
1. Access the admin panel from the user dropdown menu
2. Test database connections and email functionality
3. Edit or delete any item across the platform
4. View system logs for debugging

## 📝 Changelog

The changelog is automatically generated from git commits. Generate with:
```bash
php scripts/generate-changelog.php
```

View at: `https://claimit.stonekeep.com/changelog`

## 🔒 Security

- **OAuth 2.0**: Secure Google authentication
- **Session Security**: HTTP-only, secure cookies
- **CSRF Protection**: Tokens for all forms
- **Input Validation**: Server-side validation on all inputs
- **SQL Injection Prevention**: Prepared statements with PDO
- **XSS Prevention**: Output escaping with htmlspecialchars
- **AWS Security**: Presigned URLs with expiration

## 🎨 Customization

### Styling
- Main stylesheet: `public/assets/css/style.css`
- Uses CSS custom properties for easy theming
- Responsive breakpoints: 768px (tablet), 480px (mobile)

### Adding Features
1. Create new template in `templates/`
2. Add route to `$availablePages` in `public/index.php`
3. Add navigation link in navbar section

## 🤝 Contributing

This project is licensed under CC BY-NC-SA 4.0. See [LICENSE](LICENSE) for details.

**You are free to:**
- Share and redistribute the code
- Modify and build upon the code
- Create derivative works

**Under these conditions:**
- **Attribution** — Must credit Dave Shevett
- **NonCommercial** — Cannot use for commercial purposes or charge fees
- **ShareAlike** — Derivatives must use the same CC BY-NC-SA 4.0 license

**Trademark:** 
The "ClaimIt" name and associated branding are trademarks of Dave Shevett.
Forks and derivative works must use a different name and branding to avoid confusion with the original project.

# 🆘 Support

For commercial licensing or questions:
- Repository: https://github.com/shevett/claimit-stonekeep
- Contact: Dave Shevett (shevett@pobox.com)

## 📚 Additional Documentation

- [AWS_SETUP.md](AWS_SETUP.md) - AWS S3/CloudFront/SES configuration
- [GOOGLE_OAUTH_SETUP.md](GOOGLE_OAUTH_SETUP.md) - Google OAuth setup
- [DEPLOYMENT.md](DEPLOYMENT.md) - Production deployment guide
- [MIGRATION_SUMMARY.md](MIGRATION_SUMMARY.md) - Database migration details

---

**Created by Dave Shevett**
