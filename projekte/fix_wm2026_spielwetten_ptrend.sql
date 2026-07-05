-- Fix falsch gepflegter Trendpunkte fuer WM2026-Spielwetten.
-- Exakter Tipp: 5 Punkte, richtige Tendenz: 2 Punkte.

UPDATE tl_hy_wetten
SET Ptrend = 2
WHERE Wettbewerb = 'WM2026'
  AND UPPER(TRIM(Art)) = 'S'
  AND Kommentar IN ('Sechzehn 8', 'Sechzehn 15')
  AND Pok = 5
  AND Ptrend = 5;

SELECT ID, Wettbewerb, Kommentar, Art, Tipp1, Pok, Ptrend
FROM tl_hy_wetten
WHERE Wettbewerb = 'WM2026'
  AND UPPER(TRIM(Art)) = 'S'
  AND Kommentar IN ('Sechzehn 8', 'Sechzehn 15');
