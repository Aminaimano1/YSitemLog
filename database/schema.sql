-- Create database
CREATE DATABASE IF NOT EXISTS item_borrowing_system;
USE item_borrowing_system;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    user_password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'manager', 'supervisor') DEFAULT 'staff',
    department VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Items table
CREATE TABLE items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    grade ENUM('New', 'Used') NOT NULL DEFAULT 'New',
    quantity INT NULL DEFAULT 0,
    weight DECIMAL(10,2) NULL DEFAULT 0.00,
    item_condition VARCHAR(50) DEFAULT 'Good',
    location VARCHAR(100) DEFAULT 'Storage',
    image_path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Borrowings table
CREATE TABLE borrowings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    quantity_borrowed INT NOT NULL DEFAULT 1,
    weight_borrowed DECIMAL(10,2) NULL DEFAULT 0.00,
    date_borrowed DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_returned DATETIME NULL,
    return_condition VARCHAR(50) NULL,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Logs table
CREATE TABLE logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL
);

-- Insert default admin user (password: admin123)
INSERT INTO users (name, username, user_password, role) VALUES 
('Admin User', 'admin', 'admin123', 'admin');

-- Insert sample items
INSERT INTO items (name, category, grade, quantity, weight, item_condition, location) VALUES 
('Laptop', 'Electronics', 'New', 5, NULL, 'Good', 'Storage Room A'),
('Projector', 'Electronics', 'New', 3, NULL, 'Good', 'Storage Room B'),
('Whiteboard', 'Office Supplies', 'New', 10, NULL, 'Good', 'Storage Room C'),
('Chairs', 'Furniture', 'New', 20, NULL, 'Good', 'Storage Room D'),
('Tables', 'Furniture', 'New', 8, NULL, 'Good', 'Storage Room D'),
('Scrap Metal', 'Materials', 'Used', NULL, 150.50, 'Fair', 'Storage Room E'),
('Old Electronics', 'Electronics', 'Used', NULL, 25.75, 'Poor', 'Storage Room F'); 