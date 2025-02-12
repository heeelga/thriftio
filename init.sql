-- Creates the database if it does not exist
CREATE DATABASE IF NOT EXISTS finance;

-- Switches to the created database
USE finance;

-- Creates the `user` table if it does not exist
CREATE TABLE IF NOT EXISTS user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    firstname VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    invite TINYINT(1) DEFAULT 0,
    pruef INT(11) DEFAULT 0,
    release_notes_available TINYINT(1) DEFAULT 0,
    release_notes_read TINYINT(1) DEFAULT 0,
    changed_password TINYINT(1) DEFAULT 0,
    admin TINYINT(1) DEFAULT 0,
    error_logging TINYINT(1) DEFAULT 0,
    failed_logins INT DEFAULT 0
);

-- Creates the `logins` table
CREATE TABLE IF NOT EXISTS logins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    login_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    login_status VARCHAR(255),
    ip_address VARCHAR(255),
    city VARCHAR(255),
    country VARCHAR(255)
);

-- Creates the `savings_interest_rates` table
CREATE TABLE IF NOT EXISTS savings_interest_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    savings_name VARCHAR(255) NOT NULL UNIQUE,
    interest_rate FLOAT DEFAULT 0
);
