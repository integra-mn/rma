-- Keep the Reklamacije list fast once the numbers get long.
--
-- The list orders by LPAD(rma_number, 12, '0') so that 100000 sorts above
-- 52150 rather than below it — the number is text, and text puts '1' before
-- '5'. A plain index on rma_number cannot serve that order, because the sort is
-- on an expression, so every page load would sort the whole table.
--
-- At 39 cases nothing notices. The point of doing it now is that nobody will be
-- watching when it starts to matter: the table grows a few thousand rows a
-- year, the slowdown arrives gradually, and there is no moment where anything
-- announces itself.
--
-- The index must spell the expression exactly as the query does or the planner
-- will ignore it and everything will look fine while being no faster.
--
-- MySQL 8 would want:
--   ALTER TABLE rma_requests ADD INDEX idx_rma_number_padded ((LPAD(rma_number, 12, '0')));
-- but Postgres is what runs, and schema.sql carries the MySQL form.

CREATE INDEX IF NOT EXISTS idx_rma_number_padded
    ON rma_requests ((LPAD(rma_number, 12, '0')));
