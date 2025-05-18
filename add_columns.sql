-- Add password and is_temp_password columns to employees table
ALTER TABLE employees 
ADD COLUMN password VARCHAR(255) DEFAULT NULL,
ADD COLUMN is_temp_password TINYINT(1) NOT NULL DEFAULT 1;

-- Update existing employees to require password change
UPDATE employees SET is_temp_password = 1;
