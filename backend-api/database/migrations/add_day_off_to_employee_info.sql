-- Add day_off column to employee_info table in all outlet schemas
-- Run this for each outlet schema

-- For user_1_outlet_baru schema
SET search_path TO user_1_outlet_baru, public;

-- Check if column exists, if not add it
DO $$ 
BEGIN
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_schema = current_schema()
        AND table_name = 'employee_info' 
        AND column_name = 'day_off'
    ) THEN
        ALTER TABLE employee_info ADD COLUMN day_off INTEGER DEFAULT 0;
        COMMENT ON COLUMN employee_info.day_off IS '0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday';
    END IF;
END $$;

-- Add check constraint to ensure day_off is between 0-6
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 
        FROM pg_constraint 
        WHERE conname = 'employee_info_day_off_check'
    ) THEN
        ALTER TABLE employee_info ADD CONSTRAINT employee_info_day_off_check CHECK (day_off >= 0 AND day_off <= 6);
    END IF;
END $$;

-- Reset search path
SET search_path TO public;

-- Repeat for other outlet schemas as needed
-- Example:
-- SET search_path TO user_2_outlet_name, public;
-- [repeat the above DO blocks]
-- SET search_path TO public;
