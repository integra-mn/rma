-- Rename theme codes: light -> midnight, blue -> ocean, contrast -> focus.
-- (The legacy 'dark' theme is left untouched.) Updates every place a code is
-- persisted: users' saved preference, the default-theme setting, the per-theme
-- colour-palette setting keys, and the themes lookup table.
--
-- Idempotent: after conversion the old codes no longer exist, so re-running
-- affects 0 rows.

-- Each user's saved theme preference
UPDATE users SET theme = CASE theme
    WHEN 'light' THEN 'midnight'
    WHEN 'blue'  THEN 'ocean'
    WHEN 'contrast' THEN 'focus'
    ELSE theme END
 WHERE theme IN ('light','blue','contrast');

-- Shop-wide default theme (settings value)
UPDATE settings SET value = CASE value
    WHEN 'light' THEN 'midnight'
    WHEN 'blue'  THEN 'ocean'
    WHEN 'contrast' THEN 'focus'
    ELSE value END
 WHERE key_name = 'default_theme';

-- Per-theme colour palettes are stored under theme_colors_<code>
UPDATE settings SET key_name = 'theme_colors_midnight' WHERE key_name = 'theme_colors_light';
UPDATE settings SET key_name = 'theme_colors_ocean'    WHERE key_name = 'theme_colors_blue';
UPDATE settings SET key_name = 'theme_colors_focus'    WHERE key_name = 'theme_colors_contrast';

-- Themes lookup table (code + display name + CSS class)
UPDATE themes SET code='midnight', name='Midnight', css_class='theme-midnight' WHERE code='light';
UPDATE themes SET code='ocean',    name='Ocean',    css_class='theme-ocean'    WHERE code='blue';
UPDATE themes SET code='focus',    name='Focus',    css_class='theme-focus'    WHERE code='contrast';
