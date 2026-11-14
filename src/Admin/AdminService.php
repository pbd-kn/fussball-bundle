<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Admin;

use Doctrine\DBAL\Connection;
use PBDKN\FussballBundle\Security\ParticipantPassword;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AdminService
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function competition(): string
    {
        $row = $this->connection->fetchAssociative("SELECT value1 FROM tl_hy_config WHERE Name = ? AND aktuell = ? ORDER BY id LIMIT 1", ['Wettbewerb', 1]);
        if (!$row || $row['value1'] === '') {
            throw new \InvalidArgumentException('Bitte zuerst einen aktuellen Wettbewerb wählen.');
        }
        return (string) $row['value1'];
    }

    public function rows(string $entity): array
    {
        $schema = AdminSchema::get($entity);
        $columns = array_merge([$schema['id']], $schema['fields']);
        $sql = 'SELECT '.implode(', ', $columns).' FROM '.$schema['table'];
        $params = [];
        if ($schema['scoped']) {
            $sql .= ' WHERE Wettbewerb = ?';
            $params[] = $this->competition();
        } elseif ($entity === 'wettbewerb') {
            $sql .= ' WHERE Name = ?';
            $params[] = 'Wettbewerb';
        }
        return $this->connection->fetchAllAssociative($sql.' ORDER BY '.$schema['id'], $params);
    }

    public function row(string $entity, int $id): array
    {
        $schema = AdminSchema::get($entity);
        $sql = 'SELECT '.implode(', ', array_merge([$schema['id']], $schema['fields'])).' FROM '.$schema['table'].' WHERE '.$schema['id'].' = ?';
        $params = [$id];
        if ($schema['scoped']) {
            $sql .= ' AND Wettbewerb = ?';
            $params[] = $this->competition();
        } elseif ($entity === 'wettbewerb') {
            $sql .= ' AND Name = ?';
            $params[] = 'Wettbewerb';
        }
        $row = $this->connection->fetchAssociative($sql, $params);
        if (!$row) {
            throw new NotFoundHttpException('Der Datensatz gehört nicht zur aktuellen Verwaltung oder existiert nicht.');
        }
        return $row;
    }

    public static function text(array $data, string $key, bool $required = true, int $max = 255): string
    {
        $value = $data[$key] ?? '';
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', $value)) {
            throw new \InvalidArgumentException('Ungültiger Text im Feld '.$key.'.');
        }
        $value = trim($value);
        if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $max) {
            throw new \InvalidArgumentException('Bitte das Feld '.$key.' ausfüllen (höchstens '.$max.' Zeichen).');
        }
        return $value;
    }

    public static function integer(array $data, string $key, int $min = -1, int $max = 2147483647, ?int $default = null): int
    {
        $value = $data[$key] ?? $default;
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^-?\d{1,10}$/D', (string) $value) || (int) $value < $min || (int) $value > $max) {
            throw new \InvalidArgumentException('Ungültiger Zahlenwert im Feld '.$key.'.');
        }
        return (int) $value;
    }

    private function date(array $data, string $key): string
    {
        $value = self::text($data, $key, true, 10);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Ungültiges Datum im Feld '.$key.'.');
        }
        return $value;
    }

    private function fields(string $entity, array $data): array
    {
        switch ($entity) {
            case 'ort':
                return ['Ort' => self::text($data, 'ort'), 'Beschreibung' => self::text($data, 'beschreibung', false)];
            case 'nation':
                $image = self::text($data, 'Image');
                if (!preg_match('~^(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+\.(?:gif|png|jpg|jpeg|webp|svg)$~D', $image)
                    || !is_file(dirname(__DIR__).'/Resources/public/assets/flaggen/'.$image)) {
                    throw new \InvalidArgumentException('Bitte eine vorhandene Flagge aus dem Bundle wählen.');
                }
                return ['Nation' => self::text($data, 'Nation'), 'Type' => self::text($data, 'Type'), 'Alfa2' => self::text($data, 'Alfa2', false), 'Alfa3' => self::text($data, 'Alfa3', false), 'Domain' => self::text($data, 'Domain', false), 'Image' => $image];
            case 'mannschaft':
                $nation = self::text($data, 'nation');
                $row = $this->connection->fetchAssociative('SELECT Image FROM tl_hy_nation WHERE Nation = ?', [$nation]);
                if (!$row) {
                    throw new \InvalidArgumentException('Die gewählte Nation existiert nicht.');
                }
                return ['Name' => self::text($data, 'name'), 'Nation' => $nation, 'Gruppe' => self::text($data, 'Gruppe'), 'Flagge' => (string) $row['Image']];
            case 'spiel':
                $m1 = self::integer($data, 'M1', 1);
                $m2 = self::integer($data, 'M2', 1);
                $ort = self::integer($data, 'Ort', 1);
                $this->row('mannschaft', $m1);
                $this->row('mannschaft', $m2);
                $this->row('ort', $ort);
                if ($m1 === $m2) {
                    throw new \InvalidArgumentException('Heim- und Auswärtsmannschaft müssen verschieden sein.');
                }
                $time = self::text($data, 'Uhrzeit', true, 8);
                if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', $time)) {
                    throw new \InvalidArgumentException('Ungültige Uhrzeit.');
                }
                return ['Nr' => self::integer($data, 'Nr', 1), 'Gruppe' => self::text($data, 'Gruppe'), 'M1' => $m1, 'M2' => $m2, 'Ort' => $ort, 'Datum' => $this->date($data, 'Datum'), 'Uhrzeit' => $time, 'T1' => self::integer($data, 'T1', -1, 999), 'T2' => self::integer($data, 'T2', -1, 999)];
            case 'gruppe':
                return ['Platz' => self::integer($data, 'Platz', -1, 64)];
            case 'teilnehmer':
                $email = self::text($data, 'Email', false);
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException('Ungültige E-Mail-Adresse.');
                }
                $fields = ['Name' => self::text($data, 'Name'), 'Kurzname' => self::text($data, 'Kurzname'), 'Email' => $email];
                foreach (['Bezahlt', 'Erst', 'Sechzehn', 'Achtel', 'Viertel', 'Halb', 'Finale'] as $flag) {
                    $fields[$flag] = (string) self::integer($data, $flag, 0, 1, 0);
                }
                // Blank means preserve, not clear. No password/hash is ever sent to the browser.
                $password = $data['pwd'] ?? '';
                if (!is_string($password)) {
                    throw new \InvalidArgumentException('Ungültiges Passwort.');
                }
                if ($password !== '') {
                    $fields['pwd'] = ParticipantPassword::newHash($password);
                }
                return $fields;
            case 'wette':
                $art = strtolower(self::text($data, 'Art', true, 1));
                if (!in_array($art, ['s', 'g', 'p', 'v'], true)) {
                    throw new \InvalidArgumentException('Unbekannte Wettart.');
                }
                $fields = ['Kommentar' => self::text($data, 'Kommentar'), 'Art' => $art, 'Pok' => self::integer($data, 'Pok', 0, 1000000), 'Ptrend' => self::integer($data, 'Ptrend', 0, 1000000)];
                $fields['Tipp1'] = $art === 'g' ? self::text($data, 'Tipp1') : self::integer($data, 'Tipp1');
                foreach (['Tipp2', 'Tipp3', 'Tipp4'] as $key) {
                    $fields[$key] = self::integer($data, $key, -1, $art === 's' ? 999 : 2147483647, -1);
                }
                if ($art === 's') {
                    $this->row('spiel', (int) $fields['Tipp1']);
                } elseif ($art === 'p' && $fields['Tipp1'] !== -1) {
                    $this->row('mannschaft', (int) $fields['Tipp1']);
                } elseif ($art === 'g') {
                    if (!$this->connection->fetchOne('SELECT ID FROM tl_hy_gruppen WHERE Wettbewerb = ? AND Gruppe = ?', [$this->competition(), $fields['Tipp1']])) {
                        throw new \InvalidArgumentException('Die Gruppe existiert im aktuellen Wettbewerb nicht.');
                    }
                    foreach (['Tipp2', 'Tipp3', 'Tipp4'] as $key) {
                        if ($fields[$key] !== -1) {
                            $team = $this->row('mannschaft', $fields[$key]);
                            if ($team['Gruppe'] !== $fields['Tipp1']) {
                                throw new \InvalidArgumentException('Die Mannschaft gehört nicht zur gewählten Gruppe.');
                            }
                        }
                    }
                }
                return $fields;
        }
        throw new \InvalidArgumentException('Unbekannte Verwaltung.');
    }

    public function save(string $entity, array $data): string
    {
        AdminSchema::get($entity);
        $action = self::text($data, 'aktion', true, 1);
        $allowed = $entity === 'wettbewerb' ? ['n', 'u', 'd', 'a'] : ($entity === 'gruppe' ? ['u', 'a'] : ($entity === 'wette' ? ['n', 'b', 'd', 'u'] : ['n', 'u', 'd']));
        if (!in_array($action, $allowed, true)) {
            throw new \InvalidArgumentException('Unzulässige Aktion.');
        }
        // Multi-table changes and rebuilds either complete or roll back together.
        return $this->connection->transactional(function () use ($entity, $data, $action): string {
            if ($entity === 'wettbewerb') {
                return $this->saveCompetition($data, $action);
            }
            $competition = $this->competition();
            if ($entity === 'gruppe' && $action === 'a') {
                $this->rebuildGroups($competition);
                return 'Gruppen neu berechnet.';
            }
            if ($entity === 'wette' && $action === 'u') {
                $this->updateBetResults($competition);
                return 'Wettergebnisse aktualisiert.';
            }
            $schema = AdminSchema::get($entity);
            $id = $action === 'n' ? -1 : self::integer($data, 'ID', 1);
            if ($action !== 'n') {
                $this->row($entity, $id);
            }
            $criteria = [$schema['id'] => $id];
            if ($schema['scoped']) {
                $criteria['Wettbewerb'] = $competition;
            }
            if ($action === 'd') {
                $this->delete($entity, $id, $competition, $criteria);
                return 'Datensatz gelöscht.';
            }
            $fields = $this->fields($entity, $data);
            $this->assertUnique($entity, $fields, $id, $competition);
            $fields['tstamp'] = time();
            if ($action === 'n') {
                if ($schema['scoped']) {
                    $fields['Wettbewerb'] = $competition;
                }
                $this->connection->insert($schema['table'], $fields);
                $id = (int) $this->connection->lastInsertId();
                if ($entity === 'teilnehmer') {
                    foreach ($this->rows('wette') as $bet) {
                        $this->createParticipantBet($competition, $id, (int) $bet['ID']);
                    }
                } elseif ($entity === 'wette') {
                    foreach ($this->rows('teilnehmer') as $participant) {
                        $this->createParticipantBet($competition, (int) $participant['ID'], $id);
                    }
                }
            } else {
                $this->connection->update($schema['table'], $fields, $criteria);
            }
            return 'Datensatz gespeichert.';
        });
    }

    private function assertUnique(string $entity, array $fields, int $id, string $competition): void
    {
        $field = ['teilnehmer' => 'Kurzname', 'mannschaft' => 'Name', 'spiel' => 'Nr', 'nation' => 'Nation'][$entity] ?? null;
        if ($field === null) {
            return;
        }
        $schema = AdminSchema::get($entity);
        $sql = 'SELECT '.$schema['id'].' FROM '.$schema['table'].' WHERE '.$field.' = ? AND '.$schema['id'].' <> ?';
        $params = [$fields[$field], $id];
        if ($schema['scoped']) {
            $sql .= ' AND Wettbewerb = ?';
            $params[] = $competition;
        }
        if ($this->connection->fetchOne($sql, $params)) {
            throw new ConflictHttpException('Ein Datensatz mit diesem '.$field.' existiert bereits.');
        }
    }

    private function saveCompetition(array $data, string $action): string
    {
        $id = $action === 'n' ? -1 : self::integer($data, 'ID', 1);
        $row = $action === 'n' ? null : $this->row('wettbewerb', $id);
        if ($action === 'a') {
            $this->connection->update('tl_hy_config', ['aktuell' => 0], ['Name' => 'Wettbewerb']);
            $this->connection->update('tl_hy_config', ['aktuell' => 1, 'tstamp' => time()], ['id' => $id, 'Name' => 'Wettbewerb']);
            return 'Aktueller Wettbewerb geändert.';
        }
        if ($action === 'd') {
            foreach (AdminSchema::ENTITIES as $schema) {
                if ($schema['scoped']) {
                    $this->connection->delete($schema['table'], ['Wettbewerb' => $row['value1']]);
                }
            }
            $this->connection->delete('tl_hy_wetteaktuell', ['Wettbewerb' => $row['value1']]);
            $this->connection->delete('tl_hy_config', ['id' => $id, 'Name' => 'Wettbewerb']);
            return 'Wettbewerb und zugehörige Daten gelöscht.';
        }
        $name = $action === 'n' ? self::text($data, 'Wettbewerb', true, 64) : (string) $row['value1'];
        $start = $this->date($data, 'startDatum');
        $end = $this->date($data, 'endeDatum');
        if ($end < $start) {
            throw new \InvalidArgumentException('Das Enddatum liegt vor dem Startdatum.');
        }
        $fields = ['value1' => $name, 'value2' => (string) self::integer($data, 'anzahlGruppen', 1, 14), 'value3' => self::text($data, 'deutschlandGruppe'), 'value4' => $start, 'value5' => $end, 'tstamp' => time()];
        if ($action === 'n') {
            if ($this->connection->fetchOne('SELECT id FROM tl_hy_config WHERE Name = ? AND value1 = ?', ['Wettbewerb', $name])) {
                throw new ConflictHttpException('Dieser Wettbewerb existiert bereits.');
            }
            $this->connection->update('tl_hy_config', ['aktuell' => 0], ['Name' => 'Wettbewerb']);
            $this->connection->insert('tl_hy_config', $fields + ['Name' => 'Wettbewerb', 'aktuell' => 1]);
        } else {
            $this->connection->update('tl_hy_config', $fields, ['id' => $id, 'Name' => 'Wettbewerb']);
        }
        return 'Wettbewerb gespeichert.';
    }

    private function createParticipantBet(string $competition, int $participant, int $bet): void
    {
        $this->connection->insert('tl_hy_wetteaktuell', ['Wettbewerb' => $competition, 'Teilnehmer' => $participant, 'Wette' => $bet, 'W1' => -1, 'W2' => -1, 'W3' => -1, 'tstamp' => time()]);
    }

    private function delete(string $entity, int $id, string $competition, array $criteria): void
    {
        if ($entity === 'mannschaft' && ($this->connection->fetchOne('SELECT ID FROM tl_hy_spiele WHERE Wettbewerb = ? AND (M1 = ? OR M2 = ?)', [$competition, $id, $id])
            || $this->connection->fetchOne('SELECT ID FROM tl_hy_gruppen WHERE Wettbewerb = ? AND M1 = ?', [$competition, $id]))) {
            throw new ConflictHttpException('Die Mannschaft wird noch in Spielen oder Gruppen verwendet.');
        }
        if ($entity === 'ort' && $this->connection->fetchOne('SELECT ID FROM tl_hy_spiele WHERE Wettbewerb = ? AND Ort = ?', [$competition, $id])) {
            throw new ConflictHttpException('Der Ort wird noch in einem Spiel verwendet.');
        }
        if ($entity === 'teilnehmer' || $entity === 'wette') {
            $this->connection->delete('tl_hy_wetteaktuell', ['Wettbewerb' => $competition, $entity === 'teilnehmer' ? 'Teilnehmer' : 'Wette' => $id]);
        }
        $this->connection->delete(AdminSchema::get($entity)['table'], $criteria);
    }

    public function participantBets(int $participant): array
    {
        $this->row('teilnehmer', $participant);
        return $this->connection->fetchAllAssociative('SELECT a.ID, a.W1, a.W2, a.W3, w.Kommentar, w.Art, w.Tipp1 FROM tl_hy_wetteaktuell a INNER JOIN tl_hy_wetten w ON a.Wette = w.ID AND a.Wettbewerb = w.Wettbewerb WHERE a.Wettbewerb = ? AND a.Teilnehmer = ? ORDER BY a.ID', [$this->competition(), $participant]);
    }

    public function storeParticipantBet(array $data): string
    {
        if (self::text($data, 'aktion', true, 1) !== 's') {
            throw new \InvalidArgumentException('Unzulässige Aktion.');
        }
        return $this->connection->transactional(function () use ($data): string {
            $id = self::integer($data, 'Idwettaktuell', 1);
            $competition = $this->competition();
            $bet = $this->connection->fetchAssociative('SELECT w.Art, w.Tipp1 FROM tl_hy_wetteaktuell a INNER JOIN tl_hy_teilnehmer t ON a.Teilnehmer = t.ID AND a.Wettbewerb = t.Wettbewerb INNER JOIN tl_hy_wetten w ON a.Wette = w.ID AND a.Wettbewerb = w.Wettbewerb WHERE a.ID = ? AND a.Wettbewerb = ?', [$id, $competition]);
            if (!$bet) {
                throw new NotFoundHttpException('Die Teilnehmerwette gehört nicht zum aktuellen Wettbewerb.');
            }
            $art = strtolower((string) $bet['Art']);
            if (!in_array($art, ['s', 'g', 'p', 'v'], true)) {
                throw new \InvalidArgumentException('Unbekannte Wettart.');
            }
            $values = [];
            foreach (['W1', 'W2', 'W3'] as $key) {
                $values[$key] = self::integer($data, $key, -1, $art === 's' ? 999 : 2147483647, -1);
                if ($values[$key] !== -1 && ($art === 'g' || ($art === 'p' && $key === 'W1'))) {
                    $team = $this->row('mannschaft', $values[$key]);
                    if ($art === 'g' && (string) $team['Gruppe'] !== (string) $bet['Tipp1']) {
                        throw new \InvalidArgumentException('Die gewählte Mannschaft gehört nicht zur Wettgruppe.');
                    }
                }
            }
            if ($art === 's' && $values['W3'] !== -1) {
                throw new \InvalidArgumentException('Eine Spielwette hat nur zwei Toreingaben.');
            }
            if (in_array($art, ['p', 'v'], true) && ($values['W2'] !== -1 || $values['W3'] !== -1)) {
                throw new \InvalidArgumentException('Diese Wettart hat nur einen Tippwert.');
            }
            $this->connection->update('tl_hy_wetteaktuell', $values + ['tstamp' => time()], ['ID' => $id, 'Wettbewerb' => $competition]);
            return 'Teilnehmerwette gespeichert.';
        });
    }

    private function updateBetResults(string $competition): void
    {
        foreach ($this->rows('wette') as $bet) {
            $fields = [];
            if (strtolower((string) $bet['Art']) === 's') {
                $game = $this->row('spiel', (int) $bet['Tipp1']);
                $fields = ['Tipp2' => $game['T1'], 'Tipp3' => $game['T2']];
            } elseif (strtolower((string) $bet['Art']) === 'g') {
                $teams = $this->connection->fetchAllAssociative('SELECT M1 FROM tl_hy_gruppen WHERE Wettbewerb = ? AND Gruppe = ? ORDER BY CAST(Platz AS SIGNED), ID', [$competition, $bet['Tipp1']]);
                $fields = ['Tipp2' => $teams[0]['M1'] ?? -1, 'Tipp3' => $teams[1]['M1'] ?? -1, 'Tipp4' => $teams[2]['M1'] ?? -1];
            }
            if ($fields) {
                $this->connection->update('tl_hy_wetten', $fields + ['tstamp' => time()], ['ID' => $bet['ID'], 'Wettbewerb' => $competition]);
            }
        }
    }

    private function rebuildGroups(string $competition): void
    {
        $groups = [];
        $games = $this->rows('spiel');
        foreach ($this->rows('mannschaft') as $team) {
            $values = ['Spiele' => -1, 'Sieg' => -1, 'Unentschieden' => -1, 'Niederlage' => -1, 'Tore' => -1, 'Gegentore' => -1, 'Differenz' => -1, 'Punkte' => -1];
            foreach ($games as $game) {
                if ((int) $game['T1'] < 0 || (int) $game['T2'] < 0 || ((int) $game['M1'] !== (int) $team['ID'] && (int) $game['M2'] !== (int) $team['ID'])) {
                    continue;
                }
                foreach ($values as &$value) {
                    $value = max(0, $value);
                }
                unset($value);
                $home = (int) $game['M1'] === (int) $team['ID'];
                $scored = (int) $game[$home ? 'T1' : 'T2'];
                $conceded = (int) $game[$home ? 'T2' : 'T1'];
                ++$values['Spiele'];
                $values['Tore'] += $scored;
                $values['Gegentore'] += $conceded;
                ++$values[$scored === $conceded ? 'Unentschieden' : ($scored > $conceded ? 'Sieg' : 'Niederlage')];
                $values['Punkte'] += $scored === $conceded ? 1 : ($scored > $conceded ? 3 : 0);
                $values['Differenz'] = $values['Tore'] - $values['Gegentore'];
            }
            $old = $this->connection->fetchAssociative('SELECT ID FROM tl_hy_gruppen WHERE Wettbewerb = ? AND Gruppe = ? AND M1 = ?', [$competition, $team['Gruppe'], $team['ID']]);
            if ($old) {
                $id = $old['ID'];
                $this->connection->update('tl_hy_gruppen', $values + ['tstamp' => time()], ['ID' => $id, 'Wettbewerb' => $competition]);
            } else {
                $this->connection->insert('tl_hy_gruppen', $values + ['Wettbewerb' => $competition, 'Gruppe' => $team['Gruppe'], 'M1' => $team['ID'], 'Platz' => -1, 'tstamp' => time()]);
                $id = $this->connection->lastInsertId();
            }
            $groups[$team['Gruppe']][] = $values + ['ID' => $id];
        }
        foreach ($groups as $rows) {
            usort($rows, static fn (array $a, array $b): int => ($b['Punkte'] <=> $a['Punkte']) ?: ($b['Differenz'] <=> $a['Differenz']) ?: ($b['Tore'] <=> $a['Tore']));
            foreach ($rows as $rank => $row) {
                if ($row['Spiele'] >= 0) {
                    $this->connection->update('tl_hy_gruppen', ['Platz' => $rank + 1], ['ID' => $row['ID'], 'Wettbewerb' => $competition]);
                }
            }
        }
    }
}
