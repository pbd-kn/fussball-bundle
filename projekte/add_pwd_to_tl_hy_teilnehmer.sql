SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tl_hy_teilnehmer'
      AND COLUMN_NAME = 'pwd'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE tl_hy_teilnehmer ADD pwd varchar(255) NOT NULL default '''' AFTER Email',
    'SELECT ''tl_hy_teilnehmer.pwd exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
