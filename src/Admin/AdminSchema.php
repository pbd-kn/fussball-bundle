<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Admin;

final class AdminSchema
{
    public const LABELS = [
        'aktuell' => 'Aktuell', 'value1' => 'Wettbewerb', 'value2' => 'Gruppen', 'value3' => 'Deutschland-Gruppe', 'value4' => 'Startdatum', 'value5' => 'Enddatum',
        'M1' => 'Heim / Mannschaft', 'M2' => 'Auswärts', 'T1' => 'Tore Heim', 'T2' => 'Tore Auswärts', 'Pok' => 'Punkte exakt', 'Ptrend' => 'Punkte Trend',
        'Erst' => 'Gruppenphase', 'Sechzehn' => 'Sechzehntelfinale', 'Achtel' => 'Achtelfinale', 'Viertel' => 'Viertelfinale', 'Halb' => 'Halbfinale',
    ];

    // Only these fixed identifiers may be used in dynamically assembled SQL.
    public const ENTITIES = [
        'wettbewerb' => ['table' => 'tl_hy_config', 'id' => 'id', 'title' => 'Wettbewerbe', 'scoped' => false, 'fields' => ['aktuell', 'value1', 'value2', 'value3', 'value4', 'value5']],
        'mannschaft' => ['table' => 'tl_hy_mannschaft', 'id' => 'ID', 'title' => 'Mannschaften', 'scoped' => true, 'fields' => ['Name', 'Nation', 'Flagge', 'Gruppe']],
        'nation' => ['table' => 'tl_hy_nation', 'id' => 'ID', 'title' => 'Nationen', 'scoped' => false, 'fields' => ['Nation', 'Type', 'Alfa2', 'Alfa3', 'Domain', 'Image']],
        'ort' => ['table' => 'tl_hy_orte', 'id' => 'ID', 'title' => 'Orte', 'scoped' => true, 'fields' => ['Ort', 'Beschreibung']],
        'spiel' => ['table' => 'tl_hy_spiele', 'id' => 'ID', 'title' => 'Spiele', 'scoped' => true, 'fields' => ['Nr', 'Gruppe', 'M1', 'M2', 'Ort', 'Datum', 'Uhrzeit', 'T1', 'T2']],
        'gruppe' => ['table' => 'tl_hy_gruppen', 'id' => 'ID', 'title' => 'Gruppen', 'scoped' => true, 'fields' => ['Gruppe', 'M1', 'Platz', 'Spiele', 'Sieg', 'Unentschieden', 'Niederlage', 'Tore', 'Gegentore', 'Differenz', 'Punkte']],
        'wette' => ['table' => 'tl_hy_wetten', 'id' => 'ID', 'title' => 'Wetten', 'scoped' => true, 'fields' => ['Kommentar', 'Art', 'Pok', 'Ptrend', 'Tipp1', 'Tipp2', 'Tipp3', 'Tipp4']],
        // Passwords and hashes are never selected for a table or an edit form.
        'teilnehmer' => ['table' => 'tl_hy_teilnehmer', 'id' => 'ID', 'title' => 'Teilnehmer', 'scoped' => true, 'fields' => ['Name', 'Kurzname', 'Email', 'Bezahlt', 'Erst', 'Sechzehn', 'Achtel', 'Viertel', 'Halb', 'Finale']],
    ];

    public static function get(string $entity): array
    {
        if (!isset(self::ENTITIES[$entity])) {
            throw new \InvalidArgumentException('Unbekannte Verwaltung.');
        }
        return self::ENTITIES[$entity];
    }
}
