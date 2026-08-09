-- Add postal code to locations (shown in the Nova lokacija form, second row).
ALTER TABLE locations
  ADD COLUMN postal_code VARCHAR(20) AFTER address;
