-- Optional manual migration only.
-- The API already creates this column automatically when governor APIs run.
-- If your table uses `name` instead of `governor_name`, change the AFTER column to `name`.
ALTER TABLE governors
ADD COLUMN governor_image VARCHAR(255) NULL DEFAULT NULL AFTER governor_name;
