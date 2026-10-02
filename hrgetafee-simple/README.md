# HRGetafe: Human Resources Information System for Getafe LGU

## System Overview
A web-based HR management system designed for Getafe Local Government Unit (LGU) to streamline employee records, attendance tracking, leave management, and payroll processing.

## 📋 Tech Stack
- **Frontend**: HTML, CSS, JavaScript
- **Backend**: PHP 7.4+
- **Database**: MySQL
- **Server**: XAMPP (Apache + MySQL)
- **QR Code**: phpqrcode + html5-qrcode
- **Platform**: XAMPP / Apache Server

## 👥 User Roles
1. **HR Administrator** - Full system control, security, and data overrides
2. **HR Staff** - Employee management, payroll, report generation
3. **Department Head** - Leave approvals, team attendance monitoring
4. **Regular Employee** - Clock in/out, view records, apply for leaves

## 📁 Project Structure
htdocs/ ├── hr_system/ │ ├── config/ │ │ └── database.php │ ├── login.php │ ├── authenticate.php │ ├── logout.php │ ├── employee/ │ │ ├── dashboard.php │ │ ├── clock.php │ │ ├── records.php │ │ └── apply_leave.php │ ├── department-head/ │ │ ├── dashboard.php │ │ ├── approve_leave.php │ │ └── team_attendance.php │ ├── hr-staff/ │ │ ├── dashboard.php │ │ ├── add_employee.php │ │ ├── manage_employees.php │ │ ├── payroll.php │ │ └── generate_reports.php │ ├── hr-admin/ │ │ ├── dashboard.php │ │ ├── user_management.php │ │ └── system_settings.php │ ├── api/ │ │ ├── process_attendance.php │ │ ├── process_leave.php │ │ └── get_data.php │ ├── assets/ │ │ ├── css/ │ │ │ └── style.css │ │ ├── js/ │ │ │ └── script.js │ │ └── qrcodes/ │ └── includes/ │ └── functions.php

Code

## 🚀 Installation

### 1. Setup Database
- Open phpMyAdmin (http://localhost/phpmyadmin)
- Create new database: `hrgetafee`
- Import `database.sql` file

### 2. Download Files
- Extract all files to `C:\xampp\htdocs\hr_system\`

### 3. Access System
- Open browser: `http://localhost/hr_system/login.php`

### 4. Default Credentials
Will be provided after database setup

## 📊 Database Schema
- **users** - Login credentials, roles, permissions
- **employees** - Employee information, QR codes
- **attendance** - Clock in/out records
- **leave_requests** - Leave applications and approvals
- **payroll** - Payroll calculations
- **holidays** - Government holidays
- **leave_types** - Leave categories

## ✨ Key Features
✅ Role-based access control
✅ QR code attendance system
✅ Digital leave application
✅ Payroll processing
✅ Report generation
✅ Real-time attendance monitoring
✅ Leave balance tracking

## 📝 Development Status
- Phase 1: Database & Authentication ✅ In Progress
- Phase 2: Dashboards & Core Modules 🔄 Starting
- Phase 3: Advanced Features & Reports 📋 Planned

## 📞 Support
For questions or issues, contact the development team.

---
**Last Updated**: July 3, 2026
