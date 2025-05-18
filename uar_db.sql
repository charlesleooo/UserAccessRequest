-- Create database if not exists
CREATE DATABASE IF NOT EXISTS useraccessrequest;
USE useraccessrequest;

-- Create admin_users table first
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create access_requests table
CREATE TABLE IF NOT EXISTS `access_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `access_request_number` varchar(50) NOT NULL,
  `requestor_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `business_unit` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `access_type` varchar(100) NOT NULL,
  `system_type` text,
  `other_system_type` varchar(255) DEFAULT NULL,
  `role_access_type` text,
  `duration_type` varchar(20) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `justification` text NOT NULL,
  `request_date` varchar(100) NOT NULL,
  `submission_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(50) DEFAULT 'pending_superior',
  PRIMARY KEY (`id`),
  UNIQUE KEY `access_request_number` (`access_request_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create approval_history table
CREATE TABLE IF NOT EXISTS `approval_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `access_request_number` varchar(50) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `reviewer_role` varchar(50) NOT NULL,
  `action` varchar(20) NOT NULL,
  `review_notes` text NOT NULL,
  `review_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  KEY `reviewer_id` (`reviewer_id`),
  CONSTRAINT `approval_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `access_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `approval_history_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 