-- Erstellt die Datenbank, falls sie nicht existiert
CREATE DATABASE IF NOT EXISTS finance;

-- Wechselt zur erstellten Datenbank
USE finance;

-- Erstellt die Tabelle `user`, falls sie nicht existiert
CREATE TABLE IF NOT EXISTS user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    firstname VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    invite TINYINT(1) DEFAULT 0,
    pruef INIT(11) DEFAULT 0,
    release_notes_available TINYINT(1) DEFAULT 0,
    release_notes_read TINYINT(1) DEFAULT 0,
    changed_password TINYINT(1) DEFAULT 0,
    admin TINYINT(1) DEFAULT 0,
    error_logging TINYINT(1) DEFAULT 0,
    failed_logins INT DEFAULT 0
);

-- Tabelle `logins` erstellen
CREATE TABLE IF NOT EXISTS logins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    login_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    login_status VARCHAR(255),
    ip_address VARCHAR(255),
    city VARCHAR(255),
    country VARCHAR(255)
);

-- Tabelle `savings_interest_rates` erstellen
CREATE TABLE IF NOT EXISTS savings_interest_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    savings_name VARCHAR(255) NOT NULL UNIQUE,
    interest_rate FLOAT DEFAULT 0
);