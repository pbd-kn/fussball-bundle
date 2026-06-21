START TRANSACTION;

SET @hoyzer_wm2026_id = (
    SELECT id
    FROM tl_hy_config
    WHERE Name = 'Wettbewerb'
      AND value1 = 'WM2026'
    ORDER BY id ASC
    LIMIT 1
);

UPDATE tl_hy_config
SET aktuell = 0
WHERE Name = 'Wettbewerb';

INSERT INTO tl_hy_config (
    tstamp,
    Name,
    aktuell,
    value1,
    value2,
    value3,
    value4,
    value5,
    setDebug
)
SELECT
    UNIX_TIMESTAMP(),
    'Wettbewerb',
    1,
    'WM2026',
    '12',
    '',
    '2026-06-11',
    '2026-07-19',
    '0'
WHERE @hoyzer_wm2026_id IS NULL;

SET @hoyzer_wm2026_id = COALESCE(@hoyzer_wm2026_id, LAST_INSERT_ID());

UPDATE tl_hy_config
SET
    aktuell = 1,
    value2 = '12',
    value3 = '',
    value4 = '2026-06-11',
    value5 = '2026-07-19'
WHERE id = @hoyzer_wm2026_id;

COMMIT;
