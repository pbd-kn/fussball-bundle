# fussball-bundle

Fußball-Tippspiel für Contao 4.13 und Contao 5.7.

## Voraussetzungen

- Contao `^4.13 || ^5.7` (keine Freigabe für Contao 6).
- PHP ab 8.0 für Contao 4.13; Contao 5.7 benötigt mindestens PHP 8.3.
- Symfony FrameworkBundle/Routing 5.4, 6.4 oder 7.4 und Doctrine DBAL 2.13, 3 oder 4, soweit von der jeweiligen Contao-Version unterstützt.
- Die Verwaltungsansichten verwenden das mitgelieferte JavaScript ohne jQuery-Abhängigkeit.

## Gemeinsame Unterstützung für Contao 4.13 und 5.7

Alle 15 Inhaltselemente werden explizit über YAML-Service-Tags registriert. Die bestehenden Typnamen bleiben erhalten, damit vorhandene `tl_content`-Datensätze weiterhin zugeordnet werden. Gemeinsame DCA-Paletten ermöglichen die Bearbeitung der Elemente in beiden Versionen.

Die Routen werden über `src/Resources/config/routes.php` importiert. Symfony 5.4 liest die vorhandenen PHP-Attribute mit dem Loader `annotation`, neuere Versionen mit `attribute`. Der Import `Symfony\Component\Routing\Annotation\Route` bleibt absichtlich bestehen: Er ist in Symfony 5.4 vorhanden und wird unter Symfony 7.4 als Alias unterstützt.

Frontend-CSS wird über den `generatePage`-Hook eingebunden, nachdem Contao die Frontend-Seite aufgelöst hat. So bleibt das bisherige Layout auch dann erhalten, wenn die Bundle-Konfiguration vor dem Routing geladen wird. Unbenutzte Abhängigkeiten von neueren Contao-Diensten und dem alten FOSHttpCacheBundle sowie die alten `System::log()`-Debug-Aufrufe wurden entfernt. Konstruktoren greifen nicht mehr auf die Datenbank zu. Die Konfiguration des aktuellen Wettbewerbs wird erst beim Aufruf des jeweiligen Elements oder AJAX-Endpunkts gelesen.

Die Tabellen-DCA verwenden `Contao\DC_Table::class`; ungültige Sortier-/Beschriftungsfelder wurden korrigiert. Die SQL-Spaltennamen und gespeicherten IDs wurden nicht umbenannt. Eigene HTML5-Templates bleiben erhalten. UTF-8-Texte werden nicht mehr mit `utf8_encode()` erneut konvertiert; die Übersicht kann mehrfach pro Request gerendert werden. Fehlende Regeldateien liefern einen Ersatztext, und der falsche Tabellenname bei der Wettaktualisierung wurde korrigiert.

## Kompatibilität prüfen

Der Test verwendet die Bibliotheken einer vorhandenen Contao-Installation, aber immer den Bundle-Code dieses Checkouts. Er verbindet sich ausschließlich mit einer eigenen SQLite-Datenbank im Arbeitsspeicher und benötigt `pdo_sqlite`.

```text
php tests/compatibility.php /pfad/zur/contao-installation/vendor/autoload.php
```

Geprüft werden:

- Container-Kompilierung, eindeutige Registrierung aller 15 Typen und Konstruktoren ohne Datenbankzugriff.
- Alle 15 Backend-Vorschauen und alle 18 AJAX-Routen mit den tatsächlichen Symfony-Loadern.
- Tabellen-DCA, Inhaltselement-Paletten und CSS-Einbindung in Frontend, Backend und CLI.
- Zweimaliges Rendern der Übersicht und Beispiele der gemeinsamen Punkteberechnung.

Lokal erfolgreich geprüft mit Contao 4.13.56 / Symfony 5.4.53 / PHP 8.2.26 sowie Contao 5.7.17 / Symfony 7.4.23 / PHP 8.3.36. Zusätzlich wird die PHP-Syntax unter PHP 8.0, 8.3 und 8.4 geprüft.

Diese Prüfungen ersetzen keinen Test der kompletten Website. Vor einem Versionsumstieg in einer Kopie der Installation die Composer-Abhängigkeiten aktualisieren, Cache und Datenbankmigrationen prüfen und die vollständigen Verwaltungsabläufe und Punkteanzeige mit vorhandenen Daten testen.

## Verwaltung im Backend

Nach der Backend-Anmeldung als Administrator steht links der Bereich **Fußball** mit acht Menüpunkten bereit: Wettbewerbe, Mannschaften, Nationen, Orte, Spiele, Gruppen, Wetten und Teilnehmer. Eine zusätzliche Frontend-Anmeldung ist für die Verwaltung nicht erforderlich. Die vorhandenen Daten werden weiterverwendet; diese Verlagerung benötigt keine Datenbankmigration.

Die Verwaltungsansichten laufen innerhalb der normalen Contao-Backend-Oberfläche. Ihre Tabellen haben Trennlinien zwischen sämtlichen Zeilen und Spalten. Bestehende Verwaltungs-Inhaltselemente bleiben für vorhandene Seiten registriert, zeigen jedoch ausschließlich einen Link zum jeweiligen Backend-Modul. Öffentliche Ergebnis- und Punkteanzeigen bleiben im Frontend.

Alle 18 AJAX-Routen liegen unter dem konfigurierten Backend-Präfix, standardmäßig `/contao/fussball/`. Die bisherigen Frontend-AJAX-Pfade sind entfernt. Backend-Module und Endpunkte prüfen unabhängig voneinander die Administratorberechtigung.

## AJAX-Sicherheit

Verwaltungselemente und alle AJAX-Endpunkte verlangen eine gültige Contao-Backend-Anmeldung als Administrator. Der aktuelle Kontostatus wird zusätzlich in der Datenbank geprüft. Frontend-Mitglieder und Vorschau-Links erhalten dadurch keine Verwaltungsrechte. Eine eigenständige Teilnehmer-Anmeldung ist in diesem Bundle nicht implementiert; Tipps werden über die geschützte Verwaltung geändert.

Ohne Backend-Anmeldung greift der Contao-Backend-Anmeldeschutz. Frontend-Mitglieder erhalten dadurch keine Verwaltungsdaten. Auch eine vorhandene Backend-Anmeldung berechtigt nur Administratoren; gesperrte oder abgelaufene Konten werden abgewiesen.

Schreibzugriffe erfolgen ausschließlich per POST an feste Endpunkte mit Contao-CSRF-Token im Formularinhalt. Die früheren schreibenden GET- und Passwort-Pfad-Routen sowie der Diagnose-Endpunkt wurden entfernt. Externe Origin-/Fetch-Site-Angaben werden abgewiesen. Sensitive Antworten sind privat und dürfen nicht gespeichert werden.

SQL-Werte werden gebunden; Tabellen und Spalten kommen aus einer festen Liste. Werte und Referenzen werden serverseitig validiert. Datensätze, Teilnehmerwetten und zugehörige Mannschaften müssen zum aktuellen Wettbewerb gehören. Änderungen über mehrere Tabellen laufen in einer Transaktion. Antworten enthalten keine SQL-Diagnosen oder Dateisystempfade. Formulare, Tabellen und öffentliche Ergebnis-Templates escapen dynamische Texte. Öffentliche Ranglisten berechnen Punkte ohne Datenbank-Schreibzugriffe.

Neue Passwörter werden gehasht, nie in Formularen vorbelegt oder in Listen ausgegeben und nie als URL-Parameter verschickt. Ein leeres Passwortfeld bei einer Änderung erhält den bisherigen Wert. Die Migration `ParticipantPasswordMigration` wandelt vorhandene Klartextwerte in Hashes um. Unzulässige alte Werte (Nullzeichen oder mehr als 72 Bytes) werden unbrauchbar gemacht und müssen neu gesetzt werden. Datenbanksicherungen können weiterhin alte Klartextwerte enthalten und müssen entsprechend geschützt bleiben.

```text
php tests/security.php /pfad/zur/contao-installation/vendor/autoload.php
php tests/backend.php /pfad/zur/contao-installation/vendor/autoload.php
node tests/admin-client.test.cjs
```

Die Sicherheitsprüfungen decken verweigerte Zugriffe, CSRF-/Origin-Prüfungen, SQL-/HTML-Testeingaben, fremde Wettbewerbe, ungültige Referenzen, Passwortverarbeitung, Transaktions-Rollback, Migration und den JavaScript-Transport ab. Sie laufen ausschließlich mit einer eigenen SQLite-Datenbank im Arbeitsspeicher.

Der Backend-Test umfasst zusätzlich alle acht nativen Modul-Callbacks, Menübeschriftungen, CSS-Einbindung und die Abweisung von Frontend-Anfragen. Er wurde mit Contao 4.13.56 und 5.7.17 unter PHP 8.3.36 ausgeführt.

## Lokales Projekt Hoyzer (Stand 14.11.2026)

Das Projekt `C:\wampneu\www\co4\websites\Hoyzer` verwendet dieses Repository direkt über eine Verzeichnisverknüpfung. Contao bleibt dort auf Version 4.13.56; der Branchname führt allein kein Contao-Upgrade aus.

Composer verlangt im Projekt PHP `^8.3` und löst Abhängigkeiten für die Plattformversion `8.3.36` auf. Der VirtualHost `hoyzer.local` verwendet nach dem Apache-Neustart FastCGI mit PHP 8.3.36. Composer-/Apache-Konfiguration und Lockdatei wurden vor der Anpassung gesichert. Es wurde nur das lokale Fußball-Paket auf den Branch `contao-5.7` umgestellt, keine andere Erweiterung aktualisiert.

Die lokale Datenbank wurde vor der gezielten Fußball-Passwortmigration mit Contao gesichert. Diese Migration ist abgeschlossen; eine ebenfalls ausstehende Cookiebar-Migration wurde nicht ausgeführt. Nach der Verlagerung der Verwaltung wurde der Produktionscache neu erstellt. HTTP-Prüfungen bestätigen die Weiterleitung der neuen Verwaltungsadressen zum Backend-Login, HTTP 404 für entfernte Frontend-AJAX-URLs und HTTP 200 für das Backend-Stylesheet. Schreibzugriffe sind ausschließlich per POST erlaubt.

Der zuletzt durchgeführte Composer-Audit des bestehenden Projekts meldet darüber hinaus 51 Sicherheitsmeldungen in 16 produktiven Paketen, darunter Contao selbst und eine kritische Meldung im Comments-Bundle. Diese Paketlücken werden durch die AJAX-Anpassungen nicht behoben. Die Website ist damit noch nicht als insgesamt sicher für eine öffentliche Freigabe bewertet. Ein angemeldeter Bedienungstest im Browser steht ebenfalls noch aus.

## Separate Portierung unter co5

Die eigenständige Kopie unter C:/wampneu/www/co5/websites/Hoyzer verwendet Contao 5.7.17, PHP 8.3.36 und die Datenbank co5_hoyzer. Sie ist unter http://hoyzer5.local erreichbar und bindet dieses Repository unter C:/wampneu/www/co5/co5Bundles/fussball-bundle mit dev-portierung-nach-5.7 ein. Die ursprüngliche co4-Installation bleibt bestehen. Das ausführliche lokale Upgrade-Protokoll steht in der Datei UPGRADE-5.7.md im neuen Website-Verzeichnis.

## Automatischer Flaggen-Import

Nach dem Paketupdate die Datenbankmigration im Contao Manager vollständig ausführen. Sobald die Gallery-Creator-Tabellen vorhanden sind, legt die Migration 'Fußball: Flaggen in das Gallery-Creator-Album importieren' das Album Flaggen und die mitgelieferten Bilder an. Nach dem erstmaligen Anlegen der Tabellen gegebenenfalls erneut nach Migrationen suchen. Kein SSH nötig. Vorhandene Dateien bleiben erhalten; unvollständige Importe werden fortgesetzt. Der Bilderordner wird öffentlich verknüpft. Der manuelle Befehl fussball:import-flags bleibt verfügbar.
