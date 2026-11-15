<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Admin;

use PBDKN\FussballBundle\Security\AjaxSecurity;
use PBDKN\FussballBundle\Backend\BackendModules;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AdminView
{
    private AdminService $service;
    private AjaxSecurity $security;
    private UrlGeneratorInterface $urls;

    public function __construct(AdminService $service, AjaxSecurity $security, UrlGeneratorInterface $urls)
    {
        $this->service = $service;
        $this->security = $security;
        $this->urls = $urls;
    }

    private static function escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function url(string $operation, array $params = []): string
    {
        return $this->urls->generate('FussballRequestClass_'.$operation, $params);
    }

    public function backendNotice(string $entity): string
    {
        $url = self::escape($this->urls->generate('contao_backend', ['do' => BackendModules::moduleName($entity)]));

        return '<section class="fussball-admin-login" role="note">'
            .'<h2>Fußball-Verwaltung im Backend</h2>'
            .'<p>Die gesamte Fußball-Verwaltung befindet sich jetzt im Contao-Backend unter „Fußball“. Dort genügt die Anmeldung als Backend-Administrator.</p>'
            .'<p><a href="'.$url.'">'.self::escape(AdminSchema::get($entity)['title']).' im Backend öffnen</a></p>'
            .'</section>';
    }

    private function button(string $label, array $attributes): string
    {
        $html = '<button type="button" class="tl_submit"';
        foreach ($attributes as $key => $value) {
            $html .= ' '.$key.'="'.self::escape($value).'"';
        }
        return $html.'>'.self::escape($label).'</button> ';
    }

    public function render(string $entity, Request $request): string
    {
        $schema = AdminSchema::get($entity);
        $token = $this->security->getToken();
        $html = '<section class="fussball-admin contentverwaltung" data-fussball-admin data-token="'.self::escape($token).'">';
        $html .= '<h2>'.self::escape($schema['title']).'</h2>';
        if ($schema['scoped']) {
            $html .= '<p>Aktueller Wettbewerb: '.self::escape($this->service->competition()).'</p>';
        }
        $html .= '<div class="fussball-admin-result" role="status" aria-live="polite"></div><div class="fussball-admin-form"></div>';
        if ($entity === 'gruppe') {
            $html .= $this->button('Gruppen neu berechnen', ['data-fussball-write' => $this->url('bearbeitegruppe'), 'data-action' => 'a', 'data-id' => -1]);
        } elseif ($entity === 'wette') {
            foreach (['s' => 'Spielwette', 'g' => 'Gruppenwette', 'p' => 'Mannschaftswette', 'v' => 'Zahlenwette'] as $type => $label) {
                $html .= $this->button('Neue '.$label, ['data-fussball-read' => $this->url('anzeigewette', ['aktion' => 'n', 'ID' => -1, 'Type' => $type])]);
            }
            $html .= $this->button('Wettergebnisse aktualisieren', ['data-fussball-write' => $this->url('bearbeitewette'), 'data-action' => 'u', 'data-id' => -1]);
        } else {
            $html .= $this->button('Neu anlegen', ['data-fussball-read' => $this->url('anzeige'.$entity, ['aktion' => 'n', 'ID' => -1])]);
        }
        $rows = $this->service->rows($entity);
        $groupTeams = $entity === 'gruppe' ? $this->teams() : [];
        $sort = 'Gruppe';
        $direction = 'asc';
        if ($entity === 'gruppe') {
            $requestedSort = $request->query->get('sort', 'Gruppe');
            $sort = in_array($requestedSort, array_merge(['ID'], $schema['fields']), true) ? $requestedSort : 'Gruppe';
            $direction = $request->query->get('dir') === 'desc' ? 'desc' : 'asc';
            usort($rows, static function (array $a, array $b) use ($sort, $direction, $groupTeams): int {
                $left = $sort === 'M1' ? ($groupTeams[$a['M1']] ?? '') : ($a[$sort] ?? '');
                $right = $sort === 'M1' ? ($groupTeams[$b['M1']] ?? '') : ($b[$sort] ?? '');
                $comparison = in_array($sort, ['Gruppe', 'M1'], true) ? strnatcasecmp((string) $left, (string) $right) : ((int) $left <=> (int) $right);
                return ($direction === 'desc' ? -$comparison : $comparison)
                    ?: strnatcasecmp((string) $a['Gruppe'], (string) $b['Gruppe'])
                    ?: ((int) $a['Platz'] <=> (int) $b['Platz'])
                    ?: ((int) $a['ID'] <=> (int) $b['ID']);
            });
        }
        $html .= '<div class="fussball-admin-scroll"><table class="verwtablecss" border="1"><thead><tr><th>Aktionen</th>';
        foreach (array_merge(['ID'], $schema['fields']) as $field) {
            $label = $entity === 'gruppe' && $field === 'M1' ? 'Mannschaft' : (AdminSchema::LABELS[$field] ?? $field);
            if ($entity === 'gruppe') {
                $sortUrl = $this->urls->generate('contao_backend', ['do' => BackendModules::moduleName($entity), 'sort' => $field, 'dir' => $sort === $field && $direction === 'asc' ? 'desc' : 'asc']);
                $html .= '<th'.($sort === $field ? ' aria-sort="'.($direction === 'asc' ? 'ascending' : 'descending').'"' : '').'><a href="'.self::escape($sortUrl).'">'.self::escape($label).($sort === $field ? ($direction === 'asc' ? ' ↑' : ' ↓') : '').'</a></th>';
            } else {
                $html .= '<th>'.self::escape($label).'</th>';
            }
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $id = (int) $row[$schema['id']];
            $params = ['aktion' => $entity === 'wette' ? 'b' : 'u', 'ID' => $id];
            if ($entity === 'wette') {
                $params['Type'] = 'b';
            }
            $html .= '<tr><td class="fussball-admin-actions">'.$this->button('Bearbeiten', ['data-fussball-read' => $this->url('anzeige'.$entity, $params)]);
            if ($entity !== 'gruppe') {
                $html .= $this->button('Löschen', ['data-fussball-write' => $this->url('bearbeite'.$entity), 'data-action' => 'd', 'data-id' => $id, 'data-confirm' => 'Diesen Datensatz wirklich löschen?']);
            }
            if ($entity === 'wettbewerb') {
                $html .= $this->button('Aktuell setzen', ['data-fussball-write' => $this->url('bearbeitewettbewerb'), 'data-action' => 'a', 'data-id' => $id]);
            } elseif ($entity === 'teilnehmer') {
                $html .= $this->button('Tipps bearbeiten', ['data-fussball-read' => $this->url('teilnehmerwetten', ['ID' => $id])]);
            }
            $html .= '</td><td>'.$id.'</td>';
            foreach ($schema['fields'] as $field) {
                $value = $entity === 'gruppe' && $field === 'M1' ? ($groupTeams[$row[$field]] ?? $row[$field]) : ($row[$field] ?? '');
                $html .= '<td>'.self::escape($value).'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div></section>';
        // External script, no data-derived inline JavaScript or inline event handlers.
        return $html.'<script src="'.self::escape($request->getBasePath().'/bundles/fussball/js/fussball-admin.js').'" defer></script>';
    }

    private function hidden(string $name, $value): string
    {
        return '<input type="hidden" name="'.self::escape($name).'" value="'.self::escape($value).'">';
    }

    private function input(string $name, string $label, $value = '', string $type = 'text', bool $required = true, string $help = ''): string
    {
        $attributes = $type === 'password' ? ' autocomplete="new-password" minlength="12"' : ($type === 'number' ? ' step="1" min="-1" max="2147483647"' : ' maxlength="255"');
        return '<label>'.self::escape($label).' <input type="'.$type.'" name="'.self::escape($name).'" value="'.self::escape($value).'"'.$attributes.($required ? ' required' : '').($help !== '' ? ' title="'.self::escape($help).'"' : '').'></label><br>';
    }

    private function select(string $name, string $label, array $options, $selected = -1, string $help = ''): string
    {
        $html = '<label>'.self::escape($label).' <select name="'.self::escape($name).'"'.($help !== '' ? ' title="'.self::escape($help).'"' : '').'>';
        foreach ($options as $value => $text) {
            $html .= '<option value="'.self::escape($value).'"'.((string) $selected === (string) $value ? ' selected' : '').'>'.self::escape($text).'</option>';
        }
        return $html.'</select></label><br>';
    }

    private function teams(?string $group = null): array
    {
        $options = [-1 => 'Keine Angabe'];
        foreach ($this->service->rows('mannschaft') as $team) {
            if ($group === null || (string) $team['Gruppe'] === $group) {
                $options[(int) $team['ID']] = $team['Name'];
            }
        }
        return $options;
    }

    public function form(string $entity, string $action, int $id, string $type = ''): string
    {
        if (!in_array($action, ['n', 'u', 'b'], true) || ($entity === 'gruppe' && $action === 'n')) {
            throw new \InvalidArgumentException('Unzulässige Formularaktion.');
        }
        $row = $action === 'n' ? [] : $this->service->row($entity, $id);
        $action = $action === 'n' ? 'n' : ($entity === 'wette' ? 'b' : 'u');
        $html = '<form method="post" action="'.self::escape($this->url('bearbeite'.$entity)).'" data-fussball-form>';
        $html .= $this->hidden('aktion', $action).$this->hidden('ID', $id);
        switch ($entity) {
            case 'wettbewerb':
                $html .= $action === 'n' ? $this->input('Wettbewerb', 'Wettbewerb') : '<p>'.self::escape($row['value1']).'</p>';
                $html .= $this->input('anzahlGruppen', 'Anzahl Gruppen', $row['value2'] ?? 12, 'number');
                $html .= $this->input('deutschlandGruppe', 'Deutschland-Gruppe', $row['value3'] ?? 'A');
                $html .= $this->input('startDatum', 'Startdatum', $row['value4'] ?? '', 'date');
                $html .= $this->input('endeDatum', 'Enddatum', $row['value5'] ?? '', 'date');
                break;
            case 'ort':
                $html .= $this->input('ort', 'Ort', $row['Ort'] ?? '').$this->input('beschreibung', 'Beschreibung', $row['Beschreibung'] ?? '', 'text', false);
                break;
            case 'nation':
                foreach (['Nation', 'Type', 'Alfa2', 'Alfa3', 'Domain', 'Image'] as $field) {
                    $html .= $this->input($field, $field, $row[$field] ?? ($field === 'Type' ? 'n' : ''), 'text', in_array($field, ['Nation', 'Type', 'Image'], true));
                }
                break;
            case 'mannschaft':
                $nations = [];
                foreach ($this->service->rows('nation') as $nation) {
                    $nations[$nation['Nation']] = $nation['Nation'];
                }
                $html .= $this->input('name', 'Name', $row['Name'] ?? '').$this->select('nation', 'Nation', $nations, $row['Nation'] ?? '').$this->input('Gruppe', 'Gruppe', $row['Gruppe'] ?? 'A');
                break;
            case 'spiel':
                $places = [-1 => 'Bitte wählen'];
                foreach ($this->service->rows('ort') as $place) {
                    $places[$place['ID']] = $place['Ort'];
                }
                $html .= $this->input('Nr', 'Spielnummer', $row['Nr'] ?? 1, 'number').$this->input('Gruppe', 'Gruppe', $row['Gruppe'] ?? 'A');
                $html .= $this->select('M1', 'Heim', $this->teams(), $row['M1'] ?? -1).$this->select('M2', 'Auswärts', $this->teams(), $row['M2'] ?? -1).$this->select('Ort', 'Ort', $places, $row['Ort'] ?? -1);
                $html .= $this->input('Datum', 'Datum', $row['Datum'] ?? '', 'date').$this->input('Uhrzeit', 'Uhrzeit', substr($row['Uhrzeit'] ?? '18:00', 0, 5), 'time');
                $html .= $this->input('T1', 'Tore Heim (-1 = offen)', $row['T1'] ?? -1, 'number').$this->input('T2', 'Tore Auswärts (-1 = offen)', $row['T2'] ?? -1, 'number');
                break;
            case 'gruppe':
                $html .= '<p>Gruppe '.self::escape($row['Gruppe']).', Mannschaft '.self::escape($row['M1']).'</p>'.$this->input('Platz', 'Platz', $row['Platz'], 'number');
                break;
            case 'teilnehmer':
                foreach (['Name', 'Kurzname', 'Email'] as $field) {
                    $html .= $this->input($field, $field, $row[$field] ?? '', $field === 'Email' ? 'email' : 'text', $field !== 'Email');
                }
                $html .= $this->input('pwd', 'Neues Passwort (leer = unverändert)', '', 'password', false);
                foreach (['Bezahlt', 'Erst', 'Sechzehn', 'Achtel', 'Viertel', 'Halb', 'Finale'] as $flag) {
                    $html .= '<label><input type="checkbox" name="'.$flag.'" value="1"'.(!empty($row[$flag]) ? ' checked' : '').'> '.self::escape(AdminSchema::LABELS[$flag] ?? $flag).'</label> ';
                }
                break;
            case 'wette':
                $type = strtolower((string) ($row['Art'] ?? $type));
                if (!in_array($type, ['s', 'g', 'p', 'v'], true)) {
                    throw new \InvalidArgumentException('Unbekannte Wettart.');
                }
                $html .= $this->hidden('Art', $type).$this->input('Kommentar', 'Kommentar', $row['Kommentar'] ?? '');
                $html .= $this->input('Pok', 'Punkte exakt', $row['Pok'] ?? 3, 'number').$this->input('Ptrend', 'Punkte Trend', $row['Ptrend'] ?? 1, 'number');
                if ($type === 's') {
                    $games = [];
                    $teams = $this->teams();
                    foreach ($this->service->rows('spiel') as $game) {
                        $games[$game['ID']] = '#'.$game['Nr'].' '.($teams[$game['M1']] ?? $game['M1']).' : '.($teams[$game['M2']] ?? $game['M2']);
                    }
                    $html .= $this->select('Tipp1', 'Spiel', $games, $row['Tipp1'] ?? -1);
                } elseif ($type === 'g') {
                    $groups = [];
                    foreach ($this->service->rows('gruppe') as $group) {
                        $groups[$group['Gruppe']] = $group['Gruppe'];
                    }
                    $html .= $this->select('Tipp1', 'Gruppe', $groups, $row['Tipp1'] ?? 'A');
                } elseif ($type === 'p') {
                    $html .= $this->select('Tipp1', 'Mannschaft', $this->teams(), $row['Tipp1'] ?? -1);
                } else {
                    $html .= $this->input('Tipp1', 'Zahl', $row['Tipp1'] ?? -1, 'number');
                }
                foreach (['Tipp2', 'Tipp3', 'Tipp4'] as $field) {
                    $html .= $type === 'g' ? $this->select($field, $field.' (Mannschaft)', $this->teams(), $row[$field] ?? -1) : $this->input($field, $field, $row[$field] ?? -1, 'number');
                }
                break;
        }
        return $html.'<p><button type="submit" class="tl_submit">Speichern</button> '.$this->button('Abbrechen', ['data-fussball-cancel' => '1']).'</p></form>';
    }

    public function participantBets(int $id): string
    {
        $participant = $this->service->row('teilnehmer', $id);
        $html = '<h3>Tipps von '.self::escape($participant['Name']).'</h3>';
        $close = '<p>'.$this->button('Tipps schließen', ['data-fussball-cancel' => '1']).'</p>';
        $html .= $close;
        $games = null;
        $teamNames = [];
        foreach ($this->service->participantBets($id) as $bet) {
            $type = strtolower((string) $bet['Art']);
            $labels = ['W1' => 'Tippwert', 'W2' => 'W2', 'W3' => 'W3'];
            $help = 'Den Zahlenwert für diese Wette eingeben. -1 bedeutet: noch kein Tipp.';
            if ($type === 's') {
                if ($games === null) {
                    $games = [];
                    foreach ($this->service->rows('spiel') as $game) {
                        $games[(int) $game['ID']] = $game;
                    }
                    $teamNames = $this->teams();
                    unset($teamNames[-1]);
                }
                $game = $games[(int) $bet['Tipp1']] ?? [];
                $firstTeam = $teamNames[(int) ($game['M1'] ?? -1)] ?? 'Mannschaft 1';
                $secondTeam = $teamNames[(int) ($game['M2'] ?? -1)] ?? 'Mannschaft 2';
                $labels = ['W1' => $firstTeam, 'W2' => $secondTeam];
                $help = 'Getippte Tore für '.$firstTeam.' (erstes Feld) und '.$secondTeam.' (zweites Feld). -1 bedeutet: noch kein Tipp.';
            } elseif ($type === 'g') {
                $labels = ['W1' => '1. Platz', 'W2' => '2. Platz', 'W3' => '3. Platz'];
                $help = 'Mannschaften für die Plätze 1, 2 und 3 der Gruppe auswählen. Keine Angabe bedeutet: noch kein Tipp.';
            } elseif ($type === 'p') {
                $labels['W1'] = 'Mannschaft';
                $help = 'Die getippte Mannschaft auswählen. Keine Angabe bedeutet: noch kein Tipp.';
            } elseif ($type === 'v' && preg_match('/deutschland\s+wird/iu', (string) $bet['Kommentar'])) {
                $help = '1 = Meister; 2 = Ausscheiden im Finale; 4 = Ausscheiden im Halbfinale; 8 = Ausscheiden im Viertelfinale; 16 = Ausscheiden im Achtelfinale; 32 = Ausscheiden im Sechzehntelfinale; 64 = Ausscheiden in der Gruppenphase. -1 = noch kein Tipp.';
            }
            $html .= '<form method="post" action="'.self::escape($this->url('storeteilnehmerwette')).'" data-fussball-form data-keep-open="1"><fieldset><legend>'.self::escape($bet['Kommentar']).'</legend>';
            $html .= $this->hidden('aktion', 's').$this->hidden('Idwettaktuell', (int) $bet['ID']);
            foreach (['W1', 'W2', 'W3'] as $index => $field) {
                if (($type === 's' && $index === 2) || (in_array($type, ['p', 'v'], true) && $index > 0)) {
                    $html .= $this->hidden($field, -1);
                } elseif ($type === 'g' || $type === 'p') {
                    $html .= $this->select($field, $labels[$field], $this->teams($type === 'g' ? (string) $bet['Tipp1'] : null), $bet[$field], $help);
                } else {
                    $html .= $this->input($field, $labels[$field].' (-1 = offen)', $bet[$field], 'number', true, $help);
                }
            }
            $html .= '<button type="submit" class="tl_submit">Tipp speichern</button></fieldset></form>';
        }
        return $html.$close;
    }
}
