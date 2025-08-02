-- Migration script to update existing database for New/Used grade system
-- Run this script on your existing database

USE item_borrowing_system;

-- Add new columns to items table
ALTER TABLE items
ADD COLUMN grade ENUM('New', 'Used') NOT NULL DEFAULT 'New' AFTER category,
ADD COLUMN weight DECIMAL(10,2) NULL DEFAULT 0.00 AFTER quantity;

-- Add new column to borrowings table
ALTER TABLE borrowings
ADD COLUMN weight_borrowed DECIMAL(10,2) NULL DEFAULT 0.00 AFTER quantity_borrowed;

-- Update existing items to have 'New' grade and set weight to NULL
UPDATE items SET grade = 'New', weight = NULL WHERE grade IS NULL;

-- Update existing borrowings to have weight_borrowed = 0 for existing records
UPDATE borrowings SET weight_borrowed = 0 WHERE weight_borrowed IS NULL;

-- Update sample data to include some Used items
INSERT INTO items (name, category, grade, quantity, weight, item_condition, location) VALUES
('Scrap Metal', 'Materials', 'Used', NULL, 150.50, 'Fair', 'Storage Room E'),
('Old Electronics', 'Electronics', 'Used', NULL, 25.75, 'Poor', 'Storage Room F')
ON DUPLICATE KEY UPDATE grade = VALUES(grade), weight = VALUES(weight); 