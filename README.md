# Complaint Portal - Production Ready

A comprehensive complaint management system with intelligent auto-assignment capabilities.

## 🚀 Features

- **Smart Auto-Assignment**: Automatically assigns complaints to technicians every 5 minutes
- **Real-time Monitoring**: Live system health and performance metrics
- **Multi-role Support**: Students, Faculty, Technicians, Superadmin
- **Hostel Issues Management**: Separate system for hostel-related complaints
- **Priority-based Assignment**: Intelligent complaint prioritization
- **Workload Balancing**: Distributes assignments evenly among technicians

## 📋 Requirements

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Web Server**: Apache, Nginx, or any PHP-compatible server
- **Browser**: Modern browser with JavaScript enabled

## 🛠️ Installation

1. **Upload Files**: Upload all files to your web server
2. **Database Setup**: Import `schema.sql` to create the database structure
3. **Configuration**: Update `includes/config.php` with your database credentials
4. **Access**: Navigate to the application URL

## 🔧 Configuration

### Database Configuration
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'complaint_portal');
```

### File Permissions
Ensure the following directories are writable:
- `logs/` (for error logging)
- `backups/` (for database backups)

## 👥 User Roles

### Students/Faculty
- Submit complaints
- Track complaint status
- Vote on hostel issues and suggestions

### Technicians
- View assigned complaints
- Update complaint status
- Mark online/offline status

### Superadmin
- Manage all users
- Monitor system health
- View auto-assignment statistics
- Manage suggestions

## 🔄 Auto-Assignment System

The system automatically:
- Validates existing assignments every 5 minutes
- Assigns unassigned complaints to available technicians
- Reassigns complaints when technicians go offline
- Balances workload among technicians
- Prioritizes complaints based on age, category, and user role

## 📊 System Monitoring

Access system monitoring at: `superadmin/system_monitoring.php`

Features:
- Real-time system health score
- Technician availability status
- System alerts and notifications
- Recent activity logs

## 🔒 Security Features

- Session-based authentication
- Role-based access control
- CSRF protection
- Input sanitization
- SQL injection prevention

## 📁 File Structure

```
complaint_portal/
├── assets/           # CSS, JS, images
├── auth/            # Authentication files
├── includes/        # Core PHP files
├── superadmin/      # Superadmin interface
├── technician/      # Technician interface
├── user/           # User interface
├── ajax/           # AJAX endpoints
└── logs/           # System logs
```

## 🚨 Support

For technical support or issues:
1. Check the system monitoring dashboard
2. Review error logs in `logs/` directory
3. Ensure all file permissions are correct
4. Verify database connectivity

## 📄 License

This project is proprietary software. All rights reserved. 