-- HRGetafe Database Backup
-- Generated: 2026-07-14 07:09:24
-- Database: hrgetafee_simple

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `visible_to_roles` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`announcement_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `clock_in` datetime DEFAULT NULL,
  `clock_out` datetime DEFAULT NULL,
  `attendance_date` date DEFAULT NULL,
  `status` enum('present','absent','late') DEFAULT 'present',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `qr_scanned` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`attendance_id`),
  KEY `idx_employee_date` (`employee_id`,`clock_in`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `backup_attendance_employee3`;
CREATE TABLE `backup_attendance_employee3` (
  `attendance_id` int(11) NOT NULL DEFAULT 0,
  `employee_id` int(11) NOT NULL,
  `clock_in` datetime DEFAULT NULL,
  `clock_out` datetime DEFAULT NULL,
  `attendance_date` date DEFAULT NULL,
  `status` enum('present','absent','late') DEFAULT 'present',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `qr_scanned` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `backup_history`;
CREATE TABLE `backup_history` (
  `backup_id` int(11) NOT NULL AUTO_INCREMENT,
  `backup_name` varchar(255) NOT NULL,
  `backup_type` varchar(50) DEFAULT NULL,
  `backup_size` bigint(20) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `backup_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  PRIMARY KEY (`backup_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `backup_history_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `backup_leave_requests_employee3`;
CREATE TABLE `backup_leave_requests_employee3` (
  `leave_id` int(11) NOT NULL DEFAULT 0,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `number_of_days` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `backup_payroll_all`;
CREATE TABLE `backup_payroll_all` (
  `payroll_id` int(11) NOT NULL DEFAULT 0,
  `employee_id` int(11) NOT NULL,
  `payroll_month` int(11) DEFAULT NULL,
  `payroll_year` int(11) DEFAULT NULL,
  `gross_salary` decimal(10,2) DEFAULT NULL,
  `deductions` decimal(10,2) DEFAULT NULL,
  `net_salary` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','processed','paid') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `backup_payroll_all` (`payroll_id`, `employee_id`, `payroll_month`, `payroll_year`, `gross_salary`, `deductions`, `net_salary`, `status`, `created_at`) VALUES ('1', '4', '7', '2026', '22000.00', '2640.00', '19360.00', 'paid', '2026-07-07 12:39:02');
INSERT INTO `backup_payroll_all` (`payroll_id`, `employee_id`, `payroll_month`, `payroll_year`, `gross_salary`, `deductions`, `net_salary`, `status`, `created_at`) VALUES ('2', '3', '7', '2026', '25000.00', '3000.00', '22000.00', 'paid', '2026-07-07 12:46:36');
INSERT INTO `backup_payroll_all` (`payroll_id`, `employee_id`, `payroll_month`, `payroll_year`, `gross_salary`, `deductions`, `net_salary`, `status`, `created_at`) VALUES ('3', '5', '7', '2026', '20000.00', '4730.02', '15269.98', 'processed', '2026-07-07 14:18:20');

DROP TABLE IF EXISTS `backup_payroll_details_all`;
CREATE TABLE `backup_payroll_details_all` (
  `payroll_detail_id` int(11) NOT NULL DEFAULT 0,
  `payroll_id` int(11) NOT NULL,
  `detail_type` enum('earning','deduction') NOT NULL,
  `detail_name` varchar(100) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `backup_payroll_details_all` (`payroll_detail_id`, `payroll_id`, `detail_type`, `detail_name`, `amount`, `quantity`, `created_at`) VALUES ('1', '3', 'earning', 'Gross Salary', '20000.00', '1', '2026-07-07 14:18:20');
INSERT INTO `backup_payroll_details_all` (`payroll_detail_id`, `payroll_id`, `detail_type`, `detail_name`, `amount`, `quantity`, `created_at`) VALUES ('2', '3', 'deduction', 'SSS Contribution', '726.00', '1', '2026-07-07 14:18:20');
INSERT INTO `backup_payroll_details_all` (`payroll_detail_id`, `payroll_id`, `detail_type`, `detail_name`, `amount`, `quantity`, `created_at`) VALUES ('3', '3', 'deduction', 'PhilHealth Premium', '550.00', '1', '2026-07-07 14:18:20');
INSERT INTO `backup_payroll_details_all` (`payroll_detail_id`, `payroll_id`, `detail_type`, `detail_name`, `amount`, `quantity`, `created_at`) VALUES ('4', '3', 'deduction', 'Pag-IBIG Contribution', '400.00', '1', '2026-07-07 14:18:20');
INSERT INTO `backup_payroll_details_all` (`payroll_detail_id`, `payroll_id`, `detail_type`, `detail_name`, `amount`, `quantity`, `created_at`) VALUES ('5', '3', 'deduction', 'CPF (Consolidated Police Fund)', '971.75', '1', '2026-07-07 14:18:20');
INSERT INTO `backup_payroll_details_all` (`payroll_detail_id`, `payroll_id`, `detail_type`, `detail_name`, `amount`, `quantity`, `created_at`) VALUES ('6', '3', 'deduction', 'CBIF (Comprehensive Benefit Insurance)', '0.00', '1', '2026-07-07 14:18:20');
INSERT INTO `backup_payroll_details_all` (`payroll_detail_id`, `payroll_id`, `detail_type`, `detail_name`, `amount`, `quantity`, `created_at`) VALUES ('7', '3', 'deduction', 'MPL (Magna Carta for Public Employees)', '0.00', '1', '2026-07-07 14:18:20');
INSERT INTO `backup_payroll_details_all` (`payroll_detail_id`, `payroll_id`, `detail_type`, `detail_name`, `amount`, `quantity`, `created_at`) VALUES ('8', '3', 'deduction', 'Absence Deduction (0 × ₱500)', '0.00', '1', '2026-07-07 14:18:20');
INSERT INTO `backup_payroll_details_all` (`payroll_detail_id`, `payroll_id`, `detail_type`, `detail_name`, `amount`, `quantity`, `created_at`) VALUES ('9', '3', 'deduction', 'Late Deduction (0 × ₱100)', '0.00', '1', '2026-07-07 14:18:20');
INSERT INTO `backup_payroll_details_all` (`payroll_detail_id`, `payroll_id`, `detail_type`, `detail_name`, `amount`, `quantity`, `created_at`) VALUES ('10', '3', 'deduction', 'BIR Income Tax (12%)', '2082.27', '1', '2026-07-07 14:18:20');

DROP TABLE IF EXISTS `backup_payroll_details_employee3`;
CREATE TABLE `backup_payroll_details_employee3` (
  `payroll_detail_id` int(11) NOT NULL DEFAULT 0,
  `payroll_id` int(11) NOT NULL,
  `detail_type` enum('earning','deduction') NOT NULL,
  `detail_name` varchar(100) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `backup_payroll_employee3`;
CREATE TABLE `backup_payroll_employee3` (
  `payroll_id` int(11) NOT NULL DEFAULT 0,
  `employee_id` int(11) NOT NULL,
  `payroll_month` int(11) DEFAULT NULL,
  `payroll_year` int(11) DEFAULT NULL,
  `gross_salary` decimal(10,2) DEFAULT NULL,
  `deductions` decimal(10,2) DEFAULT NULL,
  `net_salary` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','processed','paid') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `backup_payroll_employee3` (`payroll_id`, `employee_id`, `payroll_month`, `payroll_year`, `gross_salary`, `deductions`, `net_salary`, `status`, `created_at`) VALUES ('2', '3', '7', '2026', '25000.00', '3000.00', '22000.00', 'paid', '2026-07-07 12:46:36');

DROP TABLE IF EXISTS `biometric_import_logs`;
CREATE TABLE `biometric_import_logs` (
  `import_id` int(11) NOT NULL AUTO_INCREMENT,
  `imported_by` int(11) NOT NULL,
  `import_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `filename` varchar(255) DEFAULT NULL,
  `total_records` int(11) DEFAULT NULL,
  `successful_records` int(11) DEFAULT NULL,
  `failed_records` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`import_id`),
  KEY `imported_by` (`imported_by`),
  CONSTRAINT `biometric_import_logs_ibfk_1` FOREIGN KEY (`imported_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `deduction_types`;
CREATE TABLE `deduction_types` (
  `deduction_id` int(11) NOT NULL AUTO_INCREMENT,
  `deduction_name` varchar(50) NOT NULL,
  `deduction_code` varchar(10) NOT NULL,
  `description` text DEFAULT NULL,
  `is_mandatory` tinyint(1) DEFAULT 1,
  `calculation_type` enum('fixed','percentage') DEFAULT 'fixed',
  `default_rate` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`deduction_id`),
  UNIQUE KEY `deduction_code` (`deduction_code`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `deduction_types` (`deduction_id`, `deduction_name`, `deduction_code`, `description`, `is_mandatory`, `calculation_type`, `default_rate`, `created_at`) VALUES ('1', 'SSS', 'SSS', 'Social Security System', '1', 'percentage', '3.63', '2026-07-07 14:10:39');
INSERT INTO `deduction_types` (`deduction_id`, `deduction_name`, `deduction_code`, `description`, `is_mandatory`, `calculation_type`, `default_rate`, `created_at`) VALUES ('2', 'PhilHealth', 'PH', 'PhilHealth Insurance', '1', 'percentage', '2.75', '2026-07-07 14:10:39');
INSERT INTO `deduction_types` (`deduction_id`, `deduction_name`, `deduction_code`, `description`, `is_mandatory`, `calculation_type`, `default_rate`, `created_at`) VALUES ('3', 'Pag-IBIG', 'PAGIBIG', 'Home Development Mutual Fund', '1', 'percentage', '2.00', '2026-07-07 14:10:39');
INSERT INTO `deduction_types` (`deduction_id`, `deduction_name`, `deduction_code`, `description`, `is_mandatory`, `calculation_type`, `default_rate`, `created_at`) VALUES ('4', 'BIR - Income Tax', 'BIR', 'Bureau of Internal Revenue Tax', '1', 'percentage', '0.00', '2026-07-07 14:10:39');
INSERT INTO `deduction_types` (`deduction_id`, `deduction_name`, `deduction_code`, `description`, `is_mandatory`, `calculation_type`, `default_rate`, `created_at`) VALUES ('5', 'CPF', 'CPF', 'Consolidated Police Fund', '1', 'fixed', '971.75', '2026-07-07 14:10:39');
INSERT INTO `deduction_types` (`deduction_id`, `deduction_name`, `deduction_code`, `description`, `is_mandatory`, `calculation_type`, `default_rate`, `created_at`) VALUES ('6', 'CBIF', 'CBIF', 'Comprehensive Benefit Insurance Fund', '1', 'fixed', '0.00', '2026-07-07 14:10:39');
INSERT INTO `deduction_types` (`deduction_id`, `deduction_name`, `deduction_code`, `description`, `is_mandatory`, `calculation_type`, `default_rate`, `created_at`) VALUES ('7', 'MPL', 'MPL', 'Magna Carta for Public Employees Levy', '1', 'fixed', '0.00', '2026-07-07 14:10:39');
INSERT INTO `deduction_types` (`deduction_id`, `deduction_name`, `deduction_code`, `description`, `is_mandatory`, `calculation_type`, `default_rate`, `created_at`) VALUES ('8', 'Absence Deduction', 'ABSENCE', 'Deduction for absences', '0', 'fixed', '500.00', '2026-07-07 14:10:39');
INSERT INTO `deduction_types` (`deduction_id`, `deduction_name`, `deduction_code`, `description`, `is_mandatory`, `calculation_type`, `default_rate`, `created_at`) VALUES ('9', 'Late Deduction', 'LATE', 'Deduction for late arrivals', '0', 'fixed', '100.00', '2026-07-07 14:10:39');

DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `dept_id` int(11) NOT NULL AUTO_INCREMENT,
  `dept_name` varchar(100) NOT NULL,
  `dept_head` varchar(100) DEFAULT NULL,
  `budget` decimal(12,2) DEFAULT NULL,
  `created_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`dept_id`),
  UNIQUE KEY `unique_dept_name` (`dept_name`),
  KEY `idx_departments_deleted` (`is_deleted`,`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `departments` (`dept_id`, `dept_name`, `dept_head`, `budget`, `created_date`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('1', 'Human Resources', 'HR', '0.00', '2026-07-11 21:34:05', '0', NULL, NULL);
INSERT INTO `departments` (`dept_id`, `dept_name`, `dept_head`, `budget`, `created_date`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('2', 'Finance', 'FIN', '0.00', '2026-07-11 21:34:05', '0', NULL, NULL);
INSERT INTO `departments` (`dept_id`, `dept_name`, `dept_head`, `budget`, `created_date`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('3', 'Operations', 'OPS', '0.00', '2026-07-11 21:34:05', '0', NULL, NULL);
INSERT INTO `departments` (`dept_id`, `dept_name`, `dept_head`, `budget`, `created_date`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('4', 'IT Support', 'IT', '0.00', '2026-07-11 21:34:05', '0', NULL, NULL);

DROP TABLE IF EXISTS `employee_deductions`;
CREATE TABLE `employee_deductions` (
  `emp_deduction_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `deduction_id` int(11) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`emp_deduction_id`),
  KEY `employee_id` (`employee_id`),
  KEY `deduction_id` (`deduction_id`),
  CONSTRAINT `employee_deductions_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  CONSTRAINT `employee_deductions_ibfk_2` FOREIGN KEY (`deduction_id`) REFERENCES `deduction_types` (`deduction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_code` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `date_hired` date DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `department` varchar(100) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `qr_generated_date` datetime DEFAULT NULL,
  PRIMARY KEY (`employee_id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `employees` (`employee_id`, `employee_code`, `first_name`, `last_name`, `email`, `phone`, `position`, `salary`, `date_hired`, `status`, `created_at`, `updated_at`, `department`, `qr_code`, `qr_generated_date`) VALUES ('1', 'GETAFE-2026-001', 'Admin', 'User', 'admin@getafe.gov.ph', '09123456789', 'HR Administrator', '50000.00', '2020-01-01', 'active', '2026-07-07 12:29:42', '2026-07-07 12:29:42', NULL, NULL, NULL);
INSERT INTO `employees` (`employee_id`, `employee_code`, `first_name`, `last_name`, `email`, `phone`, `position`, `salary`, `date_hired`, `status`, `created_at`, `updated_at`, `department`, `qr_code`, `qr_generated_date`) VALUES ('2', 'GETAFE-2026-002', 'Maria', 'Santos', 'maria@getafe.gov.ph', '09234567890', 'HR Staff', '35000.00', '2021-03-15', 'active', '2026-07-07 12:29:42', '2026-07-07 12:29:42', NULL, NULL, NULL);
INSERT INTO `employees` (`employee_id`, `employee_code`, `first_name`, `last_name`, `email`, `phone`, `position`, `salary`, `date_hired`, `status`, `created_at`, `updated_at`, `department`, `qr_code`, `qr_generated_date`) VALUES ('3', 'GETAFE-2026-003', 'John', 'Dela Cruz', 'john@getafe.gov.ph', '09345678901', 'Administrative Officer', '25000.00', '2022-01-20', 'active', '2026-07-07 12:29:42', '2026-07-07 12:29:42', NULL, NULL, NULL);
INSERT INTO `employees` (`employee_id`, `employee_code`, `first_name`, `last_name`, `email`, `phone`, `position`, `salary`, `date_hired`, `status`, `created_at`, `updated_at`, `department`, `qr_code`, `qr_generated_date`) VALUES ('4', 'GETAFE-2026-004', 'Rosa', 'Garcia', 'rosa@getafe.gov.ph', '09456789012', 'Clerk', '22000.00', '2022-05-10', 'active', '2026-07-07 12:29:42', '2026-07-07 12:29:42', NULL, NULL, NULL);
INSERT INTO `employees` (`employee_id`, `employee_code`, `first_name`, `last_name`, `email`, `phone`, `position`, `salary`, `date_hired`, `status`, `created_at`, `updated_at`, `department`, `qr_code`, `qr_generated_date`) VALUES ('5', 'GETAFE-2026-005', 'Pedro', 'Lopez', 'pedro@getafe.gov.ph', '09567890123', 'Assistant', '20000.00', '2023-01-15', 'active', '2026-07-07 12:29:42', '2026-07-07 12:29:42', NULL, NULL, NULL);
INSERT INTO `employees` (`employee_id`, `employee_code`, `first_name`, `last_name`, `email`, `phone`, `position`, `salary`, `date_hired`, `status`, `created_at`, `updated_at`, `department`, `qr_code`, `qr_generated_date`) VALUES ('6', 'GETAFE-2026-006', 'Janah', 'Breva', 'm.janey@gmail.com', '09701918626', '0', '200.00', '2026-07-11', 'active', '2026-07-11 20:54:00', '2026-07-11 20:54:00', NULL, NULL, NULL);
INSERT INTO `employees` (`employee_id`, `employee_code`, `first_name`, `last_name`, `email`, `phone`, `position`, `salary`, `date_hired`, `status`, `created_at`, `updated_at`, `department`, `qr_code`, `qr_generated_date`) VALUES ('7', 'GETAFE-2026-007', 'cejay', 'cutarra', 'gxsgxhxbs@gmail.com', '09999', '0', '1.00', '2026-07-14', 'active', '2026-07-14 13:01:42', '2026-07-14 13:01:42', NULL, NULL, NULL);

DROP TABLE IF EXISTS `holidays`;
CREATE TABLE `holidays` (
  `holiday_id` int(11) NOT NULL AUTO_INCREMENT,
  `holiday_name` varchar(100) NOT NULL,
  `holiday_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`holiday_id`),
  KEY `idx_holidays_deleted` (`is_deleted`,`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `holidays` (`holiday_id`, `holiday_name`, `holiday_date`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('1', 'New Year', '2026-01-01', '2026-07-07 12:29:43', '0', NULL, NULL);
INSERT INTO `holidays` (`holiday_id`, `holiday_name`, `holiday_date`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('2', 'EDSA Revolution', '2026-02-25', '2026-07-07 12:29:43', '0', NULL, NULL);
INSERT INTO `holidays` (`holiday_id`, `holiday_name`, `holiday_date`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('3', 'Labor Day', '2026-05-01', '2026-07-07 12:29:43', '0', NULL, NULL);
INSERT INTO `holidays` (`holiday_id`, `holiday_name`, `holiday_date`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('4', 'Independence Day', '2026-06-12', '2026-07-07 12:29:43', '0', NULL, NULL);
INSERT INTO `holidays` (`holiday_id`, `holiday_name`, `holiday_date`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('5', 'Christmas Day', '2026-12-25', '2026-07-07 12:29:43', '0', NULL, NULL);

DROP TABLE IF EXISTS `hours_fraction_conversion`;
CREATE TABLE `hours_fraction_conversion` (
  `conversion_id` int(11) NOT NULL AUTO_INCREMENT,
  `hours` int(11) DEFAULT NULL,
  `minutes` int(11) DEFAULT NULL,
  `fraction_of_day` decimal(5,4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`conversion_id`),
  UNIQUE KEY `hours` (`hours`,`minutes`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('1', '1', '0', '0.1250', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('2', '2', '0', '0.2500', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('3', '3', '0', '0.3750', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('4', '4', '0', '0.5000', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('5', '5', '0', '0.6250', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('6', '6', '0', '0.7500', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('7', '7', '0', '0.8750', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('8', '8', '0', '1.0000', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('9', '1', '15', '0.1313', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('10', '1', '30', '0.1375', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('11', '1', '45', '0.1438', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('12', '2', '15', '0.2438', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('13', '2', '30', '0.3125', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('14', '2', '45', '0.3438', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('15', '3', '15', '0.4063', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('16', '3', '30', '0.4375', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('17', '3', '45', '0.4688', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('18', '4', '15', '0.5313', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('19', '4', '30', '0.5625', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('20', '4', '45', '0.5938', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('21', '5', '15', '0.6563', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('22', '5', '30', '0.6875', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('23', '5', '45', '0.7188', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('24', '6', '15', '0.7813', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('25', '6', '30', '0.8125', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('26', '6', '45', '0.8438', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('27', '7', '15', '0.9063', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('28', '7', '30', '0.9375', '2026-07-07 14:10:39');
INSERT INTO `hours_fraction_conversion` (`conversion_id`, `hours`, `minutes`, `fraction_of_day`, `created_at`) VALUES ('29', '7', '45', '0.9688', '2026-07-07 14:10:39');

DROP TABLE IF EXISTS `leave_compensation`;
CREATE TABLE `leave_compensation` (
  `compensation_id` int(11) NOT NULL AUTO_INCREMENT,
  `ams_value` decimal(5,1) DEFAULT NULL,
  `leave_earned_days` decimal(5,2) DEFAULT NULL,
  `leave_earned_amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`compensation_id`),
  UNIQUE KEY `ams_value` (`ams_value`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('1', '0.5', '1.23', '1.23', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('2', '1.0', '1.20', '1.20', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('3', '1.5', '1.19', '1.19', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('4', '2.0', '1.17', '1.17', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('5', '2.5', '1.15', '1.15', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('6', '3.0', '1.13', '1.13', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('7', '3.5', '1.10', '1.10', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('8', '4.0', '1.08', '1.08', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('9', '4.5', '1.06', '1.06', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('10', '5.0', '1.04', '1.04', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('11', '5.5', '1.02', '1.02', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('12', '6.0', '1.00', '1.00', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('13', '6.5', '0.98', '0.98', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('14', '7.0', '0.96', '0.96', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('15', '7.5', '0.94', '0.94', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('16', '8.0', '0.92', '0.92', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('17', '8.5', '0.90', '0.90', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('18', '9.0', '0.88', '0.88', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('19', '9.5', '0.85', '0.85', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('20', '10.0', '0.83', '0.83', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('21', '10.5', '0.81', '0.81', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('22', '11.0', '0.79', '0.79', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('23', '11.5', '0.77', '0.77', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('24', '12.0', '0.75', '0.75', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('25', '12.5', '0.73', '0.73', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('26', '13.0', '0.71', '0.71', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('27', '13.5', '0.69', '0.69', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('28', '14.0', '0.67', '0.67', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('29', '14.5', '0.65', '0.65', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('30', '15.0', '0.63', '0.63', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('31', '15.5', '0.60', '0.60', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('32', '16.0', '0.58', '0.58', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('33', '16.5', '0.56', '0.56', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('34', '17.0', '0.54', '0.54', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('35', '17.5', '0.52', '0.52', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('36', '18.0', '0.50', '0.50', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('37', '18.5', '0.48', '0.48', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('38', '19.0', '0.46', '0.46', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('39', '19.5', '0.44', '0.44', '2026-07-07 14:10:39');
INSERT INTO `leave_compensation` (`compensation_id`, `ams_value`, `leave_earned_days`, `leave_earned_amount`, `created_at`) VALUES ('40', '20.0', '0.42', '0.42', '2026-07-07 14:10:39');

DROP TABLE IF EXISTS `leave_requests`;
CREATE TABLE `leave_requests` (
  `leave_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `number_of_days` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`leave_id`),
  KEY `employee_id` (`employee_id`),
  KEY `leave_type_id` (`leave_type_id`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`),
  CONSTRAINT `leave_requests_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `leave_type_categories`;
CREATE TABLE `leave_type_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `leave_type_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `is_paid` tinyint(1) DEFAULT 1,
  `with_pay_percentage` decimal(5,2) DEFAULT 100.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`),
  KEY `leave_type_id` (`leave_type_id`),
  CONSTRAINT `leave_type_categories_ibfk_1` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `leave_type_categories` (`category_id`, `leave_type_id`, `category_name`, `is_paid`, `with_pay_percentage`, `created_at`) VALUES ('1', '1', 'Vacation Leave With Pay', '1', '100.00', '2026-07-07 14:10:39');
INSERT INTO `leave_type_categories` (`category_id`, `leave_type_id`, `category_name`, `is_paid`, `with_pay_percentage`, `created_at`) VALUES ('2', '2', 'Sick Leave', '1', '100.00', '2026-07-07 14:10:39');
INSERT INTO `leave_type_categories` (`category_id`, `leave_type_id`, `category_name`, `is_paid`, `with_pay_percentage`, `created_at`) VALUES ('3', '3', 'Emergency Leave', '1', '100.00', '2026-07-07 14:10:39');
INSERT INTO `leave_type_categories` (`category_id`, `leave_type_id`, `category_name`, `is_paid`, `with_pay_percentage`, `created_at`) VALUES ('4', '4', 'Study Leave', '1', '100.00', '2026-07-07 14:10:39');

DROP TABLE IF EXISTS `leave_types`;
CREATE TABLE `leave_types` (
  `leave_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `leave_type_name` varchar(50) NOT NULL,
  `max_days_per_year` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`leave_type_id`),
  KEY `idx_leave_types_deleted` (`is_deleted`,`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `leave_types` (`leave_type_id`, `leave_type_name`, `max_days_per_year`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('1', 'Vacation Leave', '15', 'Annual vacation leave', '2026-07-07 12:29:43', '0', NULL, NULL);
INSERT INTO `leave_types` (`leave_type_id`, `leave_type_name`, `max_days_per_year`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('2', 'Sick Leave', '10', 'Medical leave', '2026-07-07 12:29:43', '0', NULL, NULL);
INSERT INTO `leave_types` (`leave_type_id`, `leave_type_name`, `max_days_per_year`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('3', 'Emergency Leave', '5', 'Emergency situations', '2026-07-07 12:29:43', '0', NULL, NULL);
INSERT INTO `leave_types` (`leave_type_id`, `leave_type_name`, `max_days_per_year`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('4', 'Study Leave', '3', 'Educational purposes', '2026-07-07 12:29:43', '0', NULL, NULL);
INSERT INTO `leave_types` (`leave_type_id`, `leave_type_name`, `max_days_per_year`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('7', 'Personal Leave', '5', 'Personal matters', '2026-07-07 15:08:38', '0', NULL, NULL);

DROP TABLE IF EXISTS `payroll`;
CREATE TABLE `payroll` (
  `payroll_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `payroll_month` int(11) DEFAULT NULL,
  `payroll_year` int(11) DEFAULT NULL,
  `gross_salary` decimal(10,2) DEFAULT NULL,
  `deductions` decimal(10,2) DEFAULT NULL,
  `net_salary` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','processed','paid') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`payroll_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `payroll_details`;
CREATE TABLE `payroll_details` (
  `payroll_detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `payroll_id` int(11) NOT NULL,
  `detail_type` enum('earning','deduction') NOT NULL,
  `detail_name` varchar(100) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`payroll_detail_id`),
  KEY `payroll_id` (`payroll_id`),
  CONSTRAINT `payroll_details_ibfk_1` FOREIGN KEY (`payroll_id`) REFERENCES `payroll` (`payroll_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `recycle_bin`;
CREATE TABLE `recycle_bin` (
  `bin_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_type` varchar(50) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `deleted_by` int(11) NOT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_data` longtext DEFAULT NULL COMMENT 'JSON snapshot of deleted record',
  `restored` tinyint(1) DEFAULT 0,
  `restored_by` int(11) DEFAULT NULL,
  `restored_at` timestamp NULL DEFAULT NULL,
  `permanent_delete` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`bin_id`),
  KEY `deleted_by` (`deleted_by`),
  KEY `restored_by` (`restored_by`),
  KEY `idx_item_type` (`item_type`),
  KEY `idx_item_id` (`item_id`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `idx_restored` (`restored`),
  CONSTRAINT `recycle_bin_ibfk_1` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `recycle_bin_ibfk_2` FOREIGN KEY (`restored_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `recycle_bin` (`bin_id`, `item_type`, `item_id`, `item_name`, `deleted_by`, `deleted_at`, `deleted_data`, `restored`, `restored_by`, `restored_at`, `permanent_delete`) VALUES ('1', 'permission', '50', 'view_profile', '1', '2026-07-12 10:21:15', '{\"permission_id\":50,\"permission_name\":\"view_profile\",\"role_id\":3}', '1', '1', '2026-07-12 10:21:23', '0');
INSERT INTO `recycle_bin` (`bin_id`, `item_type`, `item_id`, `item_name`, `deleted_by`, `deleted_at`, `deleted_data`, `restored`, `restored_by`, `restored_at`, `permanent_delete`) VALUES ('2', 'permission', '53', 'apply_leave', '1', '2026-07-12 10:21:34', '{\"permission_id\":53,\"permission_name\":\"apply_leave\",\"role_id\":3}', '1', '1', '2026-07-12 10:21:45', '0');
INSERT INTO `recycle_bin` (`bin_id`, `item_type`, `item_id`, `item_name`, `deleted_by`, `deleted_at`, `deleted_data`, `restored`, `restored_by`, `restored_at`, `permanent_delete`) VALUES ('3', 'department', '2', 'Finance', '1', '2026-07-12 10:21:57', '{\"dept_id\":2,\"dept_name\":\"Finance\",\"dept_head\":\"FIN\",\"budget\":\"0.00\"}', '1', '1', '2026-07-12 10:22:02', '0');

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `permission_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`permission_id`),
  UNIQUE KEY `unique_role_permission` (`role_id`,`permission_name`),
  KEY `idx_permissions_deleted` (`is_deleted`,`deleted_at`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('24', '1', 'manage_users', 'Create, edit, delete user accounts', '2026-07-11 21:20:42', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('25', '1', 'manage_roles', 'Manage roles and permissions', '2026-07-11 21:20:42', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('26', '1', 'manage_departments', 'Manage departments', '2026-07-11 21:20:42', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('27', '1', 'manage_holidays', 'Manage company holidays', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('28', '1', 'manage_employees', 'Create and edit employee records', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('29', '1', 'generate_employee_report', 'Generate employee report', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('30', '1', 'generate_attendance_report', 'Generate attendance report', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('31', '1', 'generate_leave_report', 'Generate leave report', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('32', '1', 'generate_payroll_report', 'Generate payroll report', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('33', '1', 'backup_restore', 'Backup and restore system data', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('34', '2', 'view_employees', 'View employee records', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('35', '2', 'manage_attendance', 'Import and manage attendance', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('36', '2', 'view_attendance_logs', 'View attendance logs', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('37', '2', 'generate_attendance_summary', 'Generate attendance summary', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('38', '2', 'view_leave_requests', 'View leave requests', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('39', '2', 'approve_leaves', 'Approve or deny leave requests', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('40', '2', 'view_leave_records', 'View leave records', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('41', '2', 'manage_payroll', 'Add and edit payroll', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('42', '2', 'view_payslip', 'View payslip', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('43', '2', 'print_payslip', 'Print payslip', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('44', '2', 'generate_employee_report', 'Generate employee report', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('45', '2', 'generate_attendance_report', 'Generate attendance report', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('46', '2', 'generate_leave_report', 'Generate leave report', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('47', '2', 'generate_payroll_report', 'Generate payroll report', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('48', '2', 'export_to_excel', 'Export documents to Excel', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('49', '2', 'backup_records', 'Backup employee records', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('50', '3', 'view_profile', 'View own profile', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('51', '3', 'clock_in_out', 'Clock in and out', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('52', '3', 'view_attendance_summary', 'View own attendance summary', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('53', '3', 'apply_leave', 'Apply for leave', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('54', '3', 'view_leave_status', 'View own leave status', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('55', '3', 'view_payslip_employee', 'View own payslip', '2026-07-11 21:20:43', '0', NULL, NULL);
INSERT INTO `role_permissions` (`permission_id`, `role_id`, `permission_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('56', '3', 'view_announcements', 'View announcements', '2026-07-11 21:20:43', '0', NULL, NULL);

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `role_name` (`role_name`),
  KEY `idx_roles_deleted` (`is_deleted`,`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `roles` (`role_id`, `role_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('1', 'HR Administrator', 'Full system control and configuration', '2026-07-07 12:29:42', '0', NULL, NULL);
INSERT INTO `roles` (`role_id`, `role_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('2', 'HR Staff', 'Employee and leave management', '2026-07-07 12:29:42', '0', NULL, NULL);
INSERT INTO `roles` (`role_id`, `role_name`, `description`, `created_at`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES ('3', 'Employee', 'Clock in/out and leave application', '2026-07-07 12:29:42', '0', NULL, NULL);

DROP TABLE IF EXISTS `security_logs`;
CREATE TABLE `security_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `security_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `timestamp`) VALUES ('1', '1', 'DEACTIVATE_USER', 'Deactivated user_id=3', '::1', '2026-07-11 20:22:01');
INSERT INTO `security_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `timestamp`) VALUES ('2', '1', 'REACTIVATE_USER', 'Reactivated user_id=3', '::1', '2026-07-11 20:22:18');
INSERT INTO `security_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `timestamp`) VALUES ('3', '1', 'ADD_EMPLOYEE', 'Added employee: GETAFE-2026-006', '::1', '2026-07-11 20:54:00');
INSERT INTO `security_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `timestamp`) VALUES ('4', '1', 'CREATE_USER', 'Created user \'janah\' for employee_id=6', '::1', '2026-07-11 20:55:09');
INSERT INTO `security_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `timestamp`) VALUES ('5', '1', 'DEACTIVATE_USER', 'Deactivated user_id=6', '::1', '2026-07-11 20:55:33');
INSERT INTO `security_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `timestamp`) VALUES ('6', '1', 'UPDATE_DEPARTMENT', 'Updated department_id: 2', '::1', '2026-07-11 21:35:56');
INSERT INTO `security_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `timestamp`) VALUES ('7', '1', 'DELETE_PERMISSION', 'Moved to bin: \'view_profile\' from role_id 3', '::1', '2026-07-12 10:21:15');
INSERT INTO `security_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `timestamp`) VALUES ('8', '1', 'RESTORE_FROM_BIN', 'Restored permission: view_profile', '::1', '2026-07-12 10:21:23');
INSERT INTO `security_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `timestamp`) VALUES ('9', '1', 'DELETE_PERMISSION', 'Moved to bin: \'apply_leave\' from role_id 3', '::1', '2026-07-12 10:21:34');
INSERT INTO `security_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `timestamp`) VALUES ('10', '1', 'RESTORE_FROM_BIN', 'Restored permission: apply_leave', '::1', '2026-07-12 10:21:45');
INSERT INTO `security_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `timestamp`) VALUES ('11', '1', 'RESTORE_FROM_BIN', 'Restored department: Finance', '::1', '2026-07-12 10:22:02');
INSERT INTO `security_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `timestamp`) VALUES ('12', '1', 'ADD_EMPLOYEE', 'Added employee: GETAFE-2026-007', '::1', '2026-07-14 13:01:42');
INSERT INTO `security_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `timestamp`) VALUES ('13', '1', 'CREATE_USER', 'Created user \'gxsgxhxbs\' for employee_id=7', '::1', '2026-07-14 13:03:14');
INSERT INTO `security_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `timestamp`) VALUES ('14', '1', 'DEACTIVATE_USER', 'Deactivated user_id=7', '::1', '2026-07-14 13:03:33');
INSERT INTO `security_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `timestamp`) VALUES ('15', '1', 'REACTIVATE_USER', 'Reactivated user_id=7', '::1', '2026-07-14 13:03:38');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `deactivation_reason` varchar(255) DEFAULT NULL,
  `deactivated_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  KEY `role_id` (`role_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`),
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`user_id`, `username`, `password`, `role_id`, `employee_id`, `status`, `deactivation_reason`, `deactivated_at`, `created_at`, `last_login`) VALUES ('1', 'admin', '$2y$12$4ZZ2tuvDOJv4s1hzJYtHn.z5.fmlMNQVNi1oiNwsFTBdTQFTV8Mqa', '1', '1', 'active', NULL, NULL, '2026-07-07 12:29:42', '2026-07-14 12:56:30');
INSERT INTO `users` (`user_id`, `username`, `password`, `role_id`, `employee_id`, `status`, `deactivation_reason`, `deactivated_at`, `created_at`, `last_login`) VALUES ('2', 'maria', '$2y$12$z7/C3d0LOc4gn7IXdY1dnO1jXh/r/Ti2kfccRs4EKVPkj7gYOi1Eq', '2', '2', 'active', NULL, NULL, '2026-07-07 12:29:42', '2026-07-13 21:14:03');
INSERT INTO `users` (`user_id`, `username`, `password`, `role_id`, `employee_id`, `status`, `deactivation_reason`, `deactivated_at`, `created_at`, `last_login`) VALUES ('3', 'john', '$2y$12$4QsekufabIgX.ftJJSTWseUhafMAW/K0Ck1X779x8hi.g8eTwha96', '3', '3', 'active', NULL, NULL, '2026-07-07 12:29:42', '2026-07-13 21:12:23');
INSERT INTO `users` (`user_id`, `username`, `password`, `role_id`, `employee_id`, `status`, `deactivation_reason`, `deactivated_at`, `created_at`, `last_login`) VALUES ('4', 'rosa', '$2y$12$46GPhvcOBznN0C2VhZqUGOfH.XNz86RF/Aj03p05QQbjiJYw2tUZO', '3', '4', 'active', NULL, NULL, '2026-07-07 12:29:42', '2026-07-07 20:33:59');
INSERT INTO `users` (`user_id`, `username`, `password`, `role_id`, `employee_id`, `status`, `deactivation_reason`, `deactivated_at`, `created_at`, `last_login`) VALUES ('5', 'pedro', '$2y$12$ozzMZ13PrHFWh.lcwIbhxOeHHbMmgxrubGdzvIh0NAAEv/QkkEZg6', '3', '5', 'active', NULL, NULL, '2026-07-07 12:29:42', '2026-07-07 14:53:03');
INSERT INTO `users` (`user_id`, `username`, `password`, `role_id`, `employee_id`, `status`, `deactivation_reason`, `deactivated_at`, `created_at`, `last_login`) VALUES ('6', 'janah', '$2y$12$4rvA.4BPpglKIWKq95NEnO85mTZ0jd52sdvQyjlWJcCgX.3gmWEay', '3', '6', 'inactive', 'this was just a sample', '2026-07-11 20:55:33', '2026-07-11 20:55:09', NULL);
INSERT INTO `users` (`user_id`, `username`, `password`, `role_id`, `employee_id`, `status`, `deactivation_reason`, `deactivated_at`, `created_at`, `last_login`) VALUES ('7', 'gxsgxhxbs', '$2y$12$Fl/09/ZMqFArEbPo64GEvO4phDwOTPUF.olP5WpNAYVh17nna5RSe', '3', '7', 'active', NULL, NULL, '2026-07-14 13:03:14', NULL);

SET FOREIGN_KEY_CHECKS=1;
