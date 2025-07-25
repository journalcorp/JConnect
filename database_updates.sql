-- SQL script to update activities table for attachment settings feature
-- Execute these commands in SQL Server Management Studio or your database tool

-- Check if the attachment_settings column exists
SELECT COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'activities' 
AND COLUMN_NAME = 'attachment_settings';

-- Add attachment_settings column if it doesn't exist
IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'activities' 
    AND COLUMN_NAME = 'attachment_settings'
)
BEGIN
    ALTER TABLE activities 
    ADD attachment_settings NVARCHAR(MAX) NULL;
    
    PRINT 'Added attachment_settings column to activities table';
END
ELSE
BEGIN
    PRINT 'attachment_settings column already exists';
END

-- Optional: Add default values for existing records
UPDATE activities 
SET attachment_settings = JSON_QUERY('{"require_attachment": false, "attachment_description": "", "allow_multiple_files": false, "is_file_required": false}')
WHERE attachment_settings IS NULL;

-- Verify the column was added
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    CHARACTER_MAXIMUM_LENGTH
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'activities' 
AND COLUMN_NAME = 'attachment_settings';

-- Show current table structure
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'activities'
ORDER BY ORDINAL_POSITION;
