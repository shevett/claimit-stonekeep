# ClaimIt Web Application

A modern PHP web application for managing claims efficiently and securely. 

*Test deployment commit*

## 🚀 Features

- **Modern Web Interface**: Clean, responsive design with professional UI/UX
- **Claim Management**: Submit and track various types of claims
- **Form Validation**: Client-side and server-side validation
- **Security**: CSRF protection, input sanitization, and secure session handling
- **Responsive Design**: Works seamlessly on desktop, tablet, and mobile devices
- **Professional Contact System**: Multi-channel contact information and forms

## 📁 Project Structure

```
claimit.stonekeep.com/
├── public/
│   └── index.php              # Main entry point
├── src/                       # Application source code (for future classes)
├── config/
│   └── config.php            # Application configuration
├── includes/
│   └── functions.php         # Utility functions
├── templates/
│   ├── home.php              # Homepage template
│   ├── about.php             # About page template
│   ├── claim.php             # Claim submission page
│   ├── contact.php           # Contact page template
│   └── 404.php               # Error page template
├── assets/
│   ├── css/
│   │   └── style.css         # Main stylesheet
│   ├── js/
│   │   └── app.js            # JavaScript functionality
│   └── images/               # Image assets (empty)
├── composer.json             # Composer dependencies
└── README.md                 # This file
```

## 🛠️ Requirements

- **PHP**: 8.0 or higher
- **Web Server**: Apache, Nginx, or built-in PHP server
- **Composer**: For dependency management (optional for basic setup)

## 📥 Installation

1. **Clone or download the project**:
   ```bash
   git clone <repository-url> claimit.stonekeep.com
   # OR download and extract to ~/src/claimit.stonekeep.com/
   ```

2. **Install dependencies** (optional):
   ```bash
   cd claimit.stonekeep.com
   composer install
   ```

3. **Configure your web server**:
   - **Document Root**: Point to the `public/` directory
   - **Rewrite Rules**: Enable URL rewriting if needed

4. **For development, use PHP's built-in server**:
   ```bash
   cd public
   php -S localhost:8000
   ```

5. **Open in browser**:
   ```
   http://localhost:8000
   ```

## ⚙️ Configuration

Edit `config/config.php` to customize:

- **Application settings**: Name, version, URL
- **Database configuration**: Uncomment and configure when needed
- **Error reporting**: Set to false for production
- **Security settings**: Session configuration, CSRF tokens

## 🎯 Usage

### Navigation
- **Home**: Welcome page with feature overview
- **About**: Company information and mission
- **Make a Claim**: Submit new claims with validation
- **Contact**: Multiple contact methods and contact form

### Claim Submission
1. Navigate to "Make a Claim"
2. Select claim type from dropdown
3. Provide detailed description
4. Enter claim amount
5. Provide contact email
6. Submit form (generates unique claim ID)

### Form Features
- Real-time validation
- CSRF protection
- Input sanitization
- Error handling
- Success notifications

## 🔧 Development

### Adding New Pages
1. Create template in `templates/` directory
2. Add page to `$availablePages` array in `public/index.php`
3. Add navigation link in the navbar

### Extending Functionality
- **Database Integration**: Uncomment database config in `config/config.php`
- **User Authentication**: Implement login/logout in `includes/functions.php`
- **Email Integration**: Add email sending functionality for notifications
- **File Uploads**: Extend claim forms to accept document uploads

### Custom Styling
- Modify `assets/css/style.css` for visual changes
- Use CSS custom properties for easy theme customization
- Responsive breakpoints: 768px (tablet), 480px (mobile)

### JavaScript Enhancements
- Form validation in `assets/js/app.js`
- Animation and interaction handlers
- Utility functions for formatting and UI

## 🔒 Security Features

- **CSRF Protection**: Tokens generated and validated for forms
- **Input Sanitization**: All user input is escaped and validated
- **Session Security**: Secure session configuration
- **XSS Prevention**: HTML escaping for all output
- **SQL Injection Prevention**: Prepared for database integration

## 🎨 Design Features

- **Modern UI**: Clean, professional interface
- **Responsive Layout**: Grid-based, mobile-first design
- **Color Scheme**: Professional blue/gray palette
- **Typography**: System fonts for optimal readability
- **Animations**: Smooth transitions and hover effects
- **Accessibility**: Semantic HTML and keyboard navigation

## 📱 Browser Support

- **Modern Browsers**: Chrome, Firefox, Safari, Edge (latest versions)
- **Mobile Browsers**: iOS Safari, Chrome Mobile, Samsung Internet
- **Progressive Enhancement**: Graceful degradation for older browsers

## 🚀 Deployment

### Production Checklist
1. Set `DEVELOPMENT_MODE` to `false` in `config/config.php`
2. Configure database settings
3. Set up SSL/HTTPS
4. Configure web server with proper security headers
5. Set up regular backups
6. Configure email service for notifications

### Recommended Hosting
- **Shared Hosting**: Any PHP 8.0+ hosting
- **VPS/Cloud**: DigitalOcean, AWS, Google Cloud
- **Managed Hosting**: Platform.sh, Heroku, etc.

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support

- **Email**: support@stonekeep.com
- **Phone**: 1-800-CLAIMIT (1-800-252-4648)
- **Hours**: Monday-Friday 9AM-6PM EST

## 🔄 Version History

- **v1.0.0**: Initial release with basic claim management functionality

---

**Built with ❤️ for Stonekeep.com** 