# User Access Request System

A comprehensive PHP-based system for managing and automating user access requests within an organization. This system streamlines the process of requesting, reviewing, and approving access to various systems and applications.

![GitHub License](https://img.shields.io/badge/license-MIT-blue.svg)

## 🚀 Features

- **Multi-level Approval Workflow**
  - Superior approval
  - Technical support review
  - Process owner verification
  - Admin final approval

- **Access Request Types**
  - System Application Access
  - CCTV Access
  - Role-based Access

- **Supported Systems**
  - Canvasing System
  - ERP/NAV
  - Legacy Payroll
  - HRIS
  - Legacy Purchasing
  - Custom system support

- **Request Management**
  - Create and submit access requests
  - Track request status
  - View request history
  - Email notifications for status updates

## 💻 Technologies Used

- PHP 7.4+
- MySQL/MariaDB
- HTML5/CSS3
- JavaScript (Alpine.js)
- Tailwind CSS
- PHPMailer for email notifications

## 🛠 Requirements

- PHP 7.4 or higher
- MySQL/MariaDB database
- Web server (Apache/Nginx)
- SMTP server for email notifications
- Modern web browser

## 📦 Installation

1. Clone the repository:
```bash
git clone https://github.com/charlesleooo/UserAccessRequest.git
```

2. Configure your web server to point to the project directory

3. Create a MySQL database and import the schema (database structure will be provided)

4. Configure the database connection in your configuration file

5. Set up email credentials in the configuration for notifications

6. Ensure proper file permissions are set

## 🔧 Configuration

1. Database Configuration:
   - Update database credentials in the connection file
   - Configure database host, name, user, and password

2. Email Configuration:
   - Configure SMTP settings for email notifications
   - Update email templates as needed

## 👥 User Roles

1. **Requestor**
   - Submit new access requests
   - Track request status
   - View request history

2. **Superior**
   - Review and approve/reject initial requests
   - Add review notes

3. **Technical Support**
   - Technical review of requests
   - Set up testing environments for system access

4. **Process Owner**
   - Verify business need
   - Approve/reject based on process requirements

5. **Admin**
   - Final approval authority
   - System configuration
   - User management

## 🔄 Workflow

1. Requestor submits access request
2. Superior reviews and approves/rejects
3. Technical team reviews (if approved)
4. Process owner verifies
5. Admin gives final approval
6. For system applications:
   - Testing environment setup
   - User testing phase
   - Final access granted

## 🔒 Security Features

- User authentication and authorization
- Role-based access control
- Session management
- Input validation and sanitization
- Secure email notifications

## 🛡️ Best Practices

1. **For Requestors**
   - Provide detailed justification for access needs
   - Submit requests well in advance
   - Complete all required fields accurately

2. **For Approvers**
   - Review requests promptly
   - Provide clear feedback for rejections
   - Verify user needs against security policies

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details

## 👤 Contact

Charles Leo Palomares - charlesleohermano@gmail.com
Alvin Tampus - alvintampus3@gmail.com
Jessica Vitualla - jessvitualla@gmail.com


Project Link: [https://github.com/charlesleooo/UserAccessRequest](https://github.com/charlesleooo/UserAccessRequest)

## 🙏 Acknowledgments

- [Tailwind CSS](https://tailwindcss.com)
- [Alpine.js](https://alpinejs.dev)
- [BoxIcons](https://boxicons.com)
- [PHPMailer](https://github.com/PHPMailer/PHPMailer)