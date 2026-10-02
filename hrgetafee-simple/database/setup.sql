-- HRGetafe Simplified Database
-- Drop existing tables if they exist
DROP TABLE IF EXISTS leave_requests;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS payroll;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS leave_types;
DROP TABLE IF EXISTS holidays;
DROP TABLE IF EXISTS roles;

-- Create Roles Table
CREATE TABLE roles (
  role_id INT PRIMARY KEY AUTO_INCREMENT,
  role_name VARCHAR(50) UNIQUE NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create Employees Table
CREATE TABLE employees (
  employee_id INT PRIMARY KEY AUTO_INCREMENT,
  employee_code VARCHAR(20) UNIQUE NOT NULL,
  first_name VARCHAR(50) NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  phone VARCHAR(20),
  position VARCHAR(100),
  salary DECIMAL(10, 2),
  date_hired DATE,
  status ENUM('active', 'inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create Users Table
CREATE TABLE users (
  user_id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  employee_id INT,
  status ENUM('active', 'inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login DATETIME,
  FOREIGN KEY (role_id) REFERENCES roles(role_id),
  FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
);

-- Create Leave Types Table
CREATE TABLE leave_types (
  leave_type_id INT PRIMARY KEY AUTO_INCREMENT,
  leave_type_name VARCHAR(50) NOT NULL,
  max_days_per_year INT,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create Attendance Table
CREATE TABLE attendance (
  attendance_id INT PRIMARY KEY AUTO_INCREMENT,
  employee_id INT NOT NULL,
  clock_in DATETIME,
  clock_out DATETIME,
  attendance_date DATE,
  status ENUM('present', 'absent', 'late') DEFAULT 'present',
  remarks TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
);

-- Create Leave Requests Table
CREATE TABLE leave_requests (
  leave_id INT PRIMARY KEY AUTO_INCREMENT,
  employee_id INT NOT NULL,
  leave_type_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  number_of_days INT,
  reason TEXT,
  status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
  approved_by INT,
  approved_date DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
  FOREIGN KEY (leave_type_id) REFERENCES leave_types(leave_type_id),
  FOREIGN KEY (approved_by) REFERENCES users(user_id)
);

-- Create Payroll Table
CREATE TABLE payroll (
  payroll_id INT PRIMARY KEY AUTO_INCREMENT,
  employee_id INT NOT NULL,
  payroll_month INT,
  payroll_year INT,
  gross_salary DECIMAL(10, 2),
  deductions DECIMAL(10, 2),
  net_salary DECIMAL(10, 2),
  status ENUM('pending', 'processed', 'paid') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
);

-- Create Holidays Table
CREATE TABLE holidays (
  holiday_id INT PRIMARY KEY AUTO_INCREMENT,
  holiday_name VARCHAR(100) NOT NULL,
  holiday_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert Roles
INSERT INTO roles (role_id, role_name, description) VALUES
(1, 'HR Administrator', 'Full system control and configuration'),
(2, 'HR Staff', 'Employee and leave management'),
(3, 'Employee', 'Clock in/out and leave application');

-- Insert Sample Employees
INSERT INTO employees (employee_id, employee_code, first_name, last_name, email, phone, position, salary, date_hired, status) VALUES
(1, 'GETAFE-2026-001', 'Admin', 'User', 'admin@getafe.gov.ph', '09123456789', 'HR Administrator', 50000, '2020-01-01', 'active'),
(2, 'GETAFE-2026-002', 'Maria', 'Santos', 'maria@getafe.gov.ph', '09234567890', 'HR Staff', 35000, '2021-03-15', 'active'),
(3, 'GETAFE-2026-003', 'John', 'Dela Cruz', 'john@getafe.gov.ph', '09345678901', 'Administrative Officer', 25000, '2022-01-20', 'active'),
(4, 'GETAFE-2026-004', 'Rosa', 'Garcia', 'rosa@getafe.gov.ph', '09456789012', 'Clerk', 22000, '2022-05-10', 'active'),
(5, 'GETAFE-2026-005', 'Pedro', 'Lopez', 'pedro@getafe.gov.ph', '09567890123', 'Assistant', 20000, '2023-01-15', 'active');

-- Insert Sample Users
INSERT INTO users (username, password, role_id, employee_id, status) VALUES
('admin', 'admin123', 1, 1, 'active'),
('maria', 'password123', 2, 2, 'active'),
('john', 'password123', 3, 3, 'active'),
('rosa', 'password123', 3, 4, 'active'),
('pedro', 'password123', 3, 5, 'active');

-- Insert Leave Types
INSERT INTO leave_types (leave_type_name, max_days_per_year, description) VALUES
('Vacation Leave', 15, 'Annual vacation leave'),
('Sick Leave', 10, 'Medical leave'),
('Emergency Leave', 5, 'Emergency situations'),
('Study Leave', 3, 'Educational purposes');

-- Insert Sample Holidays
INSERT INTO holidays (holiday_name, holiday_date) VALUES
('New Year', '2026-01-01'),
('EDSA Revolution', '2026-02-25'),
('Labor Day', '2026-05-01'),
('Independence Day', '2026-06-12'),
('Christmas Day', '2026-12-25');

-- Sample Attendance Records
INSERT INTO attendance (employee_id, clock_in, clock_out, attendance_date, status) VALUES
(3, '2026-07-07 08:00:00', '2026-07-07 17:00:00', '2026-07-07', 'present'),
(4, '2026-07-07 08:30:00', '2026-07-07 17:00:00', '2026-07-07', 'late'),
(5, '2026-07-07 08:00:00', NULL, '2026-07-07', 'present');

-- Sample Leave Request
INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, number_of_days, reason, status, approved_by) VALUES
(3, 1, '2026-07-15', '2026-07-17', 3, 'Family vacation', 'approved', 2),
(4, 2, '2026-07-10', '2026-07-10', 1, 'Medical appointment', 'pending', NULL);