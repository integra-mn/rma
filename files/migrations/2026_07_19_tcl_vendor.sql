-- TCL vendor row, so per-user credentials (user_vendor_credentials) can be
-- stored for it just like Apple. The shop-wide TCL config (base URL / API key /
-- enabled / warranty toggle) lives in flat settings (tcl_*), not vendor_adapters.
INSERT INTO vendors (name, slug, is_active)
VALUES ('TCL', 'tcl', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);
