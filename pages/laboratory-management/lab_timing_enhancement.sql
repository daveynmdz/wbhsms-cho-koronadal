-- Laboratory Management Timing Enhancement
-- Add timing columns to lab_order_items table for automatic timing tracking

-- Check if columns already exist and add them if needed
SET @sql = '';

-- Add started_at column
SELECT COUNT(*) INTO @column_exists FROM information_schema.columns 
WHERE table_schema = DATABASE() AND table_name = 'lab_order_items' AND column_name = 'started_at';

IF @column_exists = 0 THEN
    SET @sql = CONCAT(@sql, 'ALTER TABLE lab_order_items ADD COLUMN started_at DATETIME NULL AFTER status; ');
END IF;

-- Add completed_at column
SELECT COUNT(*) INTO @column_exists FROM information_schema.columns 
WHERE table_schema = DATABASE() AND table_name = 'lab_order_items' AND column_name = 'completed_at';

IF @column_exists = 0 THEN
    SET @sql = CONCAT(@sql, 'ALTER TABLE lab_order_items ADD COLUMN completed_at DATETIME NULL AFTER started_at; ');
END IF;

-- Add turnaround_time column (in minutes)
SELECT COUNT(*) INTO @column_exists FROM information_schema.columns 
WHERE table_schema = DATABASE() AND table_name = 'lab_order_items' AND column_name = 'turnaround_time';

IF @column_exists = 0 THEN
    SET @sql = CONCAT(@sql, 'ALTER TABLE lab_order_items ADD COLUMN turnaround_time INT NULL COMMENT "Turnaround time in minutes" AFTER completed_at; ');
END IF;

-- Add waiting_time column (in minutes)
SELECT COUNT(*) INTO @column_exists FROM information_schema.columns 
WHERE table_schema = DATABASE() AND table_name = 'lab_order_items' AND column_name = 'waiting_time';

IF @column_exists = 0 THEN
    SET @sql = CONCAT(@sql, 'ALTER TABLE lab_order_items ADD COLUMN waiting_time INT NULL COMMENT "Waiting time in minutes from order to start" AFTER turnaround_time; ');
END IF;

-- Add average_tat column to lab_orders table for overall tracking
SELECT COUNT(*) INTO @column_exists FROM information_schema.columns 
WHERE table_schema = DATABASE() AND table_name = 'lab_orders' AND column_name = 'average_tat';

IF @column_exists = 0 THEN
    SET @sql = CONCAT(@sql, 'ALTER TABLE lab_orders ADD COLUMN average_tat DECIMAL(10,2) NULL COMMENT "Average turnaround time in minutes" AFTER status; ');
END IF;

-- Execute the dynamic SQL if there are changes to make
IF LENGTH(@sql) > 0 THEN
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    SELECT 'Lab timing columns added successfully' as result;
ELSE
    SELECT 'All lab timing columns already exist' as result;
END IF;

-- Create indexes for better performance on timing queries
CREATE INDEX IF NOT EXISTS idx_lab_order_items_timing ON lab_order_items(started_at, completed_at, status);
CREATE INDEX IF NOT EXISTS idx_lab_orders_average_tat ON lab_orders(average_tat);

-- Show current table structure for verification
DESCRIBE lab_order_items;
DESCRIBE lab_orders;