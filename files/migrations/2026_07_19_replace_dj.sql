-- Replace the Montenegrin letter "đ"/"Đ" with the digraph "dj"/"Dj" in the
-- app's own stored text, to match the source-file change (lang strings, seeds).
--
-- Scope is DELIBERATELY limited to app-controlled columns — status labels and
-- notification templates. It does NOT touch user-entered data (customer names,
-- RMA notes, etc.), where "đ" is a legitimate character.
--
-- Idempotent: REPLACE on already-converted rows is a no-op, so it is safe to
-- run more than once.

UPDATE rma_statuses
   SET label_me = REPLACE(REPLACE(label_me, 'đ', 'dj'), 'Đ', 'Dj')
 WHERE label_me LIKE '%đ%' COLLATE utf8mb4_bin
    OR label_me LIKE '%Đ%' COLLATE utf8mb4_bin;

UPDATE repair_statuses
   SET label_me = REPLACE(REPLACE(label_me, 'đ', 'dj'), 'Đ', 'Dj')
 WHERE label_me LIKE '%đ%' COLLATE utf8mb4_bin
    OR label_me LIKE '%Đ%' COLLATE utf8mb4_bin;

UPDATE notification_templates
   SET subject = REPLACE(REPLACE(subject, 'đ', 'dj'), 'Đ', 'Dj'),
       body    = REPLACE(REPLACE(body,    'đ', 'dj'), 'Đ', 'Dj')
 WHERE lang = 'me'
   AND (subject LIKE '%đ%' COLLATE utf8mb4_bin
     OR subject LIKE '%Đ%' COLLATE utf8mb4_bin
     OR body    LIKE '%đ%' COLLATE utf8mb4_bin
     OR body    LIKE '%Đ%' COLLATE utf8mb4_bin);
