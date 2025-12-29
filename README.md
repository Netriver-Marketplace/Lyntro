# Lyntro - Nigerian Online Marketplace

A comprehensive, secure, and feature-rich online marketplace built specifically for Nigeria. This platform enables buyers and sellers to connect, negotiate, and transact safely with advanced security features.

## 🚀 Features

### Core Functionality
- **User Authentication**: Secure registration and login with password hashing
- **Product Management**: Upload, edit, and manage product listings
- **Shopping Cart**: Add items, manage quantities, and checkout
- **Messaging System**: Real-time chat between buyers and sellers
- **User Dashboard**: Manage products, orders, messages, and profile

### Security Features
- **Password Hashing**: Bcrypt encryption with cost factor of 12
- **SQL Injection Protection**: Parameterized queries throughout
- **Brute Force Protection**: Rate limiting and account lockout
- **CSRF Protection**: Token-based validation for all forms
- **Session Management**: Secure session handling with timeouts

### Jiji.ng-Inspired Features
- **Category Browsing**: 10+ product categories
- **Advanced Search**: Search by title, description, and filters
- **Location Filtering**: Products filtered by Nigerian states
- **Seller Ratings**: Transparent rating and review system
- **Featured Products**: Highlighted items on homepage

### User Experience
- **Dark/Light Theme**: Toggle between themes
- **Fully Responsive**: Optimized for mobile, tablet, and desktop
- **Real-time Updates**: AJAX-powered interactions
- **Loading States**: Visual feedback during operations
- **Error Handling**: Clear error messages and recovery

## 📋 Pages Included

1. **Home** (index.html) - Landing page with featured products and categories
2. **Products** (products.html) - Browse and search all products
3. **Login** (login.html) - User authentication
4. **Register** (register.html) - New user registration
5. **Dashboard** (dashboard.html) - User management center
6. **Messages** (messages.html) - Buyer-seller communication
7. **Cart** (cart.html) - Shopping cart and checkout
8. **About** (about.html) - Company information
9. **Contact** (contact.html) - Support and contact form

## 🗂️ File Structure

```
lyntro-marketplace/
├── config.php           # Configuration and security functions
├── auth.php            # Authentication system
├── products.php        # Product management API
├── cart.php            # Shopping cart system
├── messages.php        # Messaging system
├── database.sql        # Database schema
├── styles.css          # Main stylesheet with themes
├── script.js           # JavaScript functionality
├── index.html          # Homepage
├── products.html       # Product listings
├── login.html          # Login page
├── register.html       # Registration page
├── dashboard.html      # User dashboard
├── messages.html       # Messages page
├── cart.html           # Shopping cart
├── about.html          # About page
├── contact.html        # Contact page
└── uploads/            # Upload directory (auto-created)
    ├── products/       # Product images
    └── profiles/       # User profile images
```

## 🛠️ Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Modern web browser

### Setup Steps

1. **Clone or download the project files**

2. **Import the database schema**
   ```bash
   mysql -u root -p < database.sql
   ```
   
   The database will be created with the name `lyntro_marketplace` (update the SQL file if you want a different name)

3. **Configure database connection**
   Edit `config.php` and update database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'lyntro_marketplace');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   ```

4. **Set file permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 uploads/products/
   chmod 755 uploads/profiles/
   ```

5. **Configure web server**
   - Point your web server to the project directory
   - Ensure PHP is properly configured
   - Enable required PHP extensions: pdo_mysql, json, mbstring

6. **Access the application**
   Open your browser and navigate to `http://localhost/` or your configured domain

## 🔒 Security Configuration

### Default Security Settings
- Max login attempts: 5
- Lockout duration: 30 minutes
- Password minimum length: 8 characters
- Session timeout: 1 hour
- CSRF token expiry: 1 hour
- Max file upload size: 5MB

### Production Security Checklist
- [ ] Change default database credentials
- [ ] Enable HTTPS (SSL certificate)
- [ ] Set `session.cookie_secure` to true in `config.php`
- [ ] Configure proper file permissions
- [ ] Set up regular database backups
- [ ] Enable error logging only (disable display)
- [ ] Configure firewall rules
- [ ] Set up monitoring and alerts

## 📱 Responsive Design

The marketplace is fully responsive and works on:
- **Mobile phones** (320px+)
- **Tablets** (768px+)
- **Desktop computers** (1024px+)

### Breakpoints
- Mobile: < 768px
- Tablet: 768px - 1024px
- Desktop: > 1024px

## 🎨 Theme Options

Users can toggle between:
- **Light Theme** - Clean, bright interface
- **Dark Theme** - Easy on the eyes for night browsing

Theme preference is saved in localStorage.

## 🌐 Categories

1. Electronics - Phones, laptops, and electronic devices
2. Vehicles - Cars, motorcycles, and vehicle parts
3. Property - Real estate, houses, and land
4. Fashion - Clothing, shoes, and accessories
5. Home & Garden - Furniture, appliances, and home decor
6. Jobs - Job vacancies and services
7. Services - Professional and personal services
8. Animals & Pets - Pets and pet supplies
9. Sports & Hobbies - Sports equipment and hobby items
10. Health & Beauty - Health products and beauty supplies

## 💰 Payment Methods

Supported payment options:
- Cash on Delivery
- Bank Transfer
- Paystack (integration ready)

## 🤝 API Endpoints

### Authentication
- `POST auth.php?action=register` - Register new user
- `POST auth.php?action=login` - Login user
- `POST auth.php?action=logout` - Logout user
- `POST auth.php?action=update_profile` - Update profile
- `POST auth.php?action=change_password` - Change password

### Products
- `GET products.php?action=list` - List products
- `GET products.php?action=detail&id={id}` - Get product details
- `GET products.php?action=my_products` - Get user's products
- `GET products.php?action=categories` - Get categories
- `GET products.php?action=featured` - Get featured products
- `POST products.php?action=add` - Add new product
- `POST products.php?action=update` - Update product
- `POST products.php?action=delete` - Delete product

### Cart
- `GET cart.php?action=get` - Get cart items
- `GET cart.php?action=orders` - Get user orders
- `POST cart.php?action=add` - Add item to cart
- `POST cart.php?action=update` - Update cart item
- `POST cart.php?action=remove` - Remove item from cart
- `POST cart.php?action=checkout` - Checkout

### Messages
- `GET messages.php?action=conversations` - Get conversations list
- `GET messages.php?action=conversation&user_id={id}` - Get messages
- `GET messages.php?action=unread_count` - Get unread count
- `POST messages.php?action=send` - Send message
- `POST messages.php?action=mark_read` - Mark as read
- `POST messages.php?action=delete` - Delete message

## 🧪 Testing

### Manual Testing Checklist
- [ ] User registration
- [ ] User login/logout
- [ ] Password reset
- [ ] Product upload
- [ ] Product search and filtering
- [ ] Add to cart
- [ ] Checkout process
- [ ] Send/receive messages
- [ ] Update profile
- [ ] Theme switching
- [ ] Responsive design on different devices

### Security Testing
- [ ] SQL injection attempts
- [ ] XSS attacks
- [ ] CSRF attacks
- [ ] Brute force attacks
- [ ] Session hijacking

## 🐛 Troubleshooting

### Common Issues

**Database connection failed**
- Check database credentials in `config.php`
- Ensure MySQL service is running
- Verify database exists

**File upload not working**
- Check `uploads/` directory permissions
- Verify PHP upload_max_filesize setting
- Ensure file type is allowed

**Session not persisting**
- Check PHP session configuration
- Verify cookie settings in browser
- Ensure server has write permissions

## 📞 Support

For support and inquiries:
- Email: support@lyntro.ng
- Phone: +234 800 123 4567
- Location: 123 Victoria Island, Lagos, Nigeria

## 📄 License

This project is proprietary software. All rights reserved.

## 👥 Credits

Built with ❤️ in Nigeria by the Lyntro development team.

## 🔄 Updates

### Version 1.0.0 (Current)
- Initial release
- Core marketplace functionality
- Security features
- Responsive design
- Dark/light themes

### Planned Features
- Push notifications
- Advanced analytics dashboard
- Mobile app
- Multi-language support
- Advanced payment gateway integrations
- Seller verification system
- Dispute resolution system

---

**Lyntro - Nigeria's Trusted Online Marketplace**