# Item Borrowing System

A comprehensive web-based Item Borrowing System built with PHP, MySQL, HTML/CSS/JS, and Bootstrap. This system allows organizations to manage their inventory, track item borrowing and returns, and maintain detailed activity logs.

## Features

### 🔐 User Authentication
- Secure login/logout system with PHP sessions
- Two user roles: Admin and Staff
- Password hashing with bcrypt
- Session management

### 📦 Item Management (Admin)
- Add, edit, and delete items
- Track item details (name, category, quantity, condition, location)
- Search and filter items
- Pagination for large inventories
- Low stock alerts

### 🤝 Borrowing System
- Staff and admin can borrow items
- Purpose tracking for each borrowing
- Automatic quantity reduction when items are borrowed
- Current borrowing status display

### 🔄 Return System
- Mark items as returned with condition assessment
- Automatic quantity restoration
- Return history tracking
- Overdue item detection (7+ days)

### 📊 Dashboard
- Real-time statistics and overview
- Low stock item alerts
- Overdue item notifications
- Recent activity feed
- Visual indicators for different statuses

### 👥 User Management (Admin)
- Add, edit, and delete users
- Role assignment (admin/staff)
- User activity tracking
- Prevent deletion of users with active borrowings

### 📋 Activity Logs
- Comprehensive system activity tracking
- Filter logs by user, item, action, and date range
- Export logs to CSV
- Detailed audit trail

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript
- **Framework**: Bootstrap 5.3
- **Icons**: Font Awesome 6.0
- **Database Access**: PDO with prepared statements

## Installation

### Prerequisites
- Web server (Apache/Nginx)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- PHP extensions: PDO, PDO_MySQL

### Setup Instructions

1. **Clone or download the project**
   ```bash
   git clone <repository-url>
   cd ItemLog
   ```

2. **Database Setup**
   - Create a MySQL database named `item_borrowing_system`
   - Import the database schema:
   ```bash
   mysql -u root -p item_borrowing_system < database/schema.sql
   ```

3. **Configuration**
   - Edit `config/database.php` with your database credentials:
   ```php
   private $host = "localhost";
   private $db_name = "item_borrowing_system";
   private $username = "your_username";
   private $password = "your_password";
   ```

4. **Web Server Configuration**
   - Place the project in your web server's document root
   - Ensure the web server has read/write permissions
   - Configure your web server to serve from the project directory

5. **Access the Application**
   - Navigate to `http://localhost/ItemLog` (or your configured URL)
   - Login with default admin credentials:
     - Email: `admin@example.com`
     - Password: `admin123`

## Default Data

The system comes with sample data:
- **Admin User**: admin@example.com / admin123
- **Sample Items**: Laptop, Projector, Whiteboard, Chairs, Tables

## File Structure

```
ItemLog/
├── config/
│   └── database.php          # Database configuration
├── database/
│   └── schema.sql           # Database schema and sample data
├── includes/
│   ├── functions.php        # Utility functions
│   ├── header.php          # Page header template
│   └── footer.php          # Page footer template
├── login.php               # Login page
├── logout.php              # Logout functionality
├── dashboard.php           # Main dashboard
├── items.php              # Item management
├── borrow.php             # Borrow items
├── return.php             # Return items
├── users.php              # User management (admin)
├── logs.php               # Activity logs
├── export_logs.php        # CSV export functionality
├── index.php              # Main entry point
└── README.md              # This file
```

## Security Features

- **SQL Injection Prevention**: All queries use prepared statements
- **XSS Protection**: Input sanitization and output escaping
- **Password Security**: bcrypt hashing for passwords
- **Session Security**: Secure session management
- **Access Control**: Role-based access control
- **Input Validation**: Server-side and client-side validation

## Usage Guide

### For Staff Users
1. **Login** with your credentials
2. **View Dashboard** to see current statistics
3. **Browse Items** to see available inventory
4. **Borrow Items** by selecting an item and providing a purpose
5. **Return Items** when finished using them
6. **View Logs** to see your activity history

### For Admin Users
1. **Manage Items**: Add, edit, or delete inventory items
2. **Manage Users**: Create and manage user accounts
3. **Monitor System**: View comprehensive logs and statistics
4. **Export Data**: Download activity logs as CSV files
5. **All Staff Features**: Can perform all staff operations

## Customization

### Adding New Item Categories
Edit the items.php file and add new options to the category dropdown in the add/edit forms.

### Changing Overdue Period
Modify the `isOverdue()` function in `includes/functions.php` to change the default 7-day period.

### Styling
The system uses Bootstrap 5.3. You can customize the appearance by:
- Modifying the CSS in `includes/header.php`
- Adding custom CSS files
- Overriding Bootstrap classes

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Verify database credentials in `config/database.php`
   - Ensure MySQL service is running
   - Check database name exists

2. **Permission Errors**
   - Ensure web server has read/write permissions
   - Check file ownership

3. **Session Issues**
   - Verify PHP session configuration
   - Check session storage permissions

4. **Page Not Found**
   - Verify web server configuration
   - Check file paths and permissions

### Error Logs
Check your web server's error logs for detailed error information.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is open source and available under the [MIT License](LICENSE).

## Support

For support or questions, please create an issue in the repository or contact the development team.

---

**Note**: This system is designed for internal organizational use. For production deployment, consider additional security measures such as HTTPS, firewall configuration, and regular security updates. 