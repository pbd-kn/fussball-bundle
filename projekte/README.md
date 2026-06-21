# Hoyzer Projekte

Dieses Verzeichnis enthaelt versionierte Setup-Dateien fuer Hoyzer-Wettbewerbe.

Ein Hoyzer-Projekt besteht im Bundle aus:

- einem Eintrag in `tl_hy_config` mit `Name='Wettbewerb'`
- einem passenden Regeltext unter `regeln/regel_<wettbewerb>.php`
- Wettbewerbsdaten in `tl_hy_mannschaft`, `tl_hy_gruppen`, `tl_hy_orte`, `tl_hy_spiele` und `tl_hy_wetten`

Der aktuell aktive Wettbewerb wird ueber `tl_hy_config.aktuell = 1` bestimmt.

## WM2026

`hoyzer_wm2026.sql` legt den Wettbewerb `WM2026` an und setzt ihn als aktuellen Wettbewerb. Die Datei ist bewusst idempotent: vorhandene `WM2026`-Eintraege werden aktualisiert, fehlende werden angelegt.

Die eigentlichen Mannschafts-, Spiel- und Wett-Daten werden weiterhin ueber die Hoyzer-Verwaltung gepflegt.
