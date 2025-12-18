<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Controller\ContentElement\FE;

use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\Environment;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;
use Doctrine\DBAL\Connection;
use FOS\HttpCacheBundle\Http\SymfonyResponseTagger;
use PBDKN\FussballBundle\Controller\ContentElement\AbstractFussballController;
use PBDKN\FussballBundle\Controller\ContentElement\DependencyAggregate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

/**
 * @ContentElement(FeEndstandController::TYPE, category="fussball-FE")
 */
class FeEndstandController extends AbstractFussballController
{
    public const TYPE = 'AnzeigeEndstand';

    protected ContaoFramework $framework;
    protected Connection $connection;
    protected ?SymfonyResponseTagger $responseTagger;
    protected ?ContentModel $model = null;
    protected ?PageModel $pageModel = null;
    protected TwigEnvironment $twig;
    protected HtmlDecoder $htmlDecoder;

    protected Adapter $config;
    protected Adapter $environment;
    protected Adapter $input;
    protected Adapter $stringUtil;

    public function __construct(
        DependencyAggregate $dependencyAggregate,
        ContaoFramework $framework,
        TwigEnvironment $twig,
        HtmlDecoder $htmlDecoder,
        ?SymfonyResponseTagger $responseTagger
    ) {
        parent::__construct($dependencyAggregate);

        $this->framework = $framework;
        $this->twig = $twig;
        $this->htmlDecoder = $htmlDecoder;
        $this->responseTagger = $responseTagger;

        // Adapter
        $this->config = $this->framework->getAdapter(Config::class);
        $this->environment = $this->framework->getAdapter(Environment::class);
        $this->input = $this->framework->getAdapter(Input::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
    }

    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null, PageModel $pageModel = null): Response
    {
        if ($this->scopeMatcher->isBackendRequest($request)) {
            return new Response(
                $this->twig->render('@Fussball/Backend/backend_element_view.html.twig', [
                    'Wettbewerb' => $this->aktWettbewerb['aktWettbewerb'],
                ])
            );
        }

        $this->model = $model;
        $this->pageModel = $pageModel;

        if (isset($_GET['auto_item']) && '' !== $_GET['auto_item']) {
            $this->input->setGet('auto_item', $_GET['auto_item']);
        }

        return parent::__invoke($request, $this->model, $section, $classes);
    }

    protected function getResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        $conn = $this->connection;
        $Wettbewerb = (string) $this->aktWettbewerb['aktWettbewerb'];

        // Debug im Template immer vorhanden, aber klappbar (default zu)
        $debug = false;

        // Teilnehmer + WetteAktuell (alles, inkl. Flags der gewetteten Teams)
        [$teilnehmerByArt, $teilnehmerAktWetten] = $this->loadTeilnehmerUndWettenAktuell($conn, $Wettbewerb, $debug);

        // Wetten (für P/V)
        $wetten = $this->loadWetten($conn, $Wettbewerb, $debug);

        // Punkte komplett neu berechnen -> erst DB resetten, dann in-memory rechnen, dann final schreiben
        $this->loescheTlnPunkte($conn, $Wettbewerb);

        // Alle Teilnehmer (unique) als Basis für Summen
        $allTeilnehmer = $this->uniqueTeilnehmerFromAny($teilnehmerByArt);
        if (count($allTeilnehmer) === 0) {
            $tpl = new FrontendTemplate('ce_fe_endstand');
            $tpl->data = [
                'debug' => $debug,
                'wettbewerb' => $Wettbewerb,
                'emptyMessage' => 'es existieren noch keine Teilnehmer',
                'sections' => [],
                'rangliste' => [],
            ];
            $tpl->fussballUtil = $this->fussballUtil;
            $tpl->aktWettbewerb = $this->aktWettbewerb;

            return new Response($tpl->parse(), Response::HTTP_OK, ['content-type' => 'text/html']);
        }

        // In-memory Punkte je Teilnehmer
        $punkteSumme = [];
        foreach ($allTeilnehmer as $tln) {
            $punkteSumme[(string)$tln['ID']] = 0;
        }

        $sections = [];

        // Deutschlandspiele (Gruppe aus Config + Nation-Filter Deutschland)
        $deutschlandgruppe = (string) ($this->aktWettbewerb['aktDGruppe'] ?? '');
        $sections[] = $this->createSpieleSection(
            $conn,
            $Wettbewerb,
            $deutschlandgruppe,
            $allTeilnehmer,
            $punkteSumme,
            'Deutschland Gruppenspiele',
            'Deutschland'
        );

        // Gruppen A–L (Gruppenstände + Gruppenwetten)
        $sections[] = $this->createGruppenSection(
            $conn,
            $Wettbewerb,
            $allTeilnehmer,
            $punkteSumme,
            'Gruppen A–L'
        );

        // KO-Phasen
        $sections[] = $this->createSpieleSection($conn, $Wettbewerb, 'sechz%',  $allTeilnehmer, $punkteSumme, 'Sechzehntelfinale', '');
        $sections[] = $this->createSpieleSection($conn, $Wettbewerb, 'achtel%', $allTeilnehmer, $punkteSumme, 'Achtelfinale', '');
        $sections[] = $this->createSpieleSection($conn, $Wettbewerb, 'viertel%',$allTeilnehmer, $punkteSumme, 'Viertelfinale', '');
        $sections[] = $this->createSpieleSection($conn, $Wettbewerb, 'halb%',   $allTeilnehmer, $punkteSumme, 'Halbfinale', '');
        $sections[] = $this->createSpieleSection($conn, $Wettbewerb, 'finale',  $allTeilnehmer, $punkteSumme, 'Finale', '');

        // Platz & Vergleich (P/V)
        $sections[] = $this->createPuVSection(
            $conn,
            $Wettbewerb,
            $wetten,
            $teilnehmerAktWetten,
            $punkteSumme,
            ['p', 'v'],
            'Platz und Vergleich'
        );

        // Final: Punkte in DB schreiben (einmal pro Teilnehmer => keine Mehrfachwertung möglich)
        $this->writeFinalPoints($conn, $Wettbewerb, $punkteSumme);

        // Rangliste laden
        $rangliste = $this->loadRangliste($conn, $Wettbewerb);

        $data = [
            'debug' => $debug,
            'wettbewerb' => $Wettbewerb,
            'sections' => $sections,
            'rangliste' => $rangliste,
        ];

        $tpl = new FrontendTemplate('ce_fe_endstand');
        $tpl->data = $data;
        $tpl->fussballUtil = $this->fussballUtil;
        $tpl->aktWettbewerb = $this->aktWettbewerb;

        return new Response($tpl->parse(), Response::HTTP_OK, ['content-type' => 'text/html']);
    }

    /* =========================================================
     *  Punkte-Logik (wie gehabt)
     * ========================================================= */

    private function loescheTlnPunkte(Connection $conn, string $Wettbewerb): void
    {
        $conn->executeStatement("UPDATE tl_hy_teilnehmer SET Punkte=0 WHERE Wettbewerb=?", [$Wettbewerb]);
    }

    private function berechnePunkte(string $Art, $W1, $W2, $Tipp1, $Tipp2, $Tipp3, $Pok, $Ptrend): int
    {
        if (strtolower($Art) === 's') {
            if ($W1 == -1 || $W2 == -1) return 0;
            if ($Tipp2 == -1 || $Tipp3 == -1) return 0;

            if ($W1 == $Tipp2 && $W2 == $Tipp3) {
                return (int) $Pok;
            }

            $erg = 0;
            if ($Tipp2 == $Tipp3) $erg = 0;
            else if ($Tipp2 > $Tipp3) $erg = -1;
            else $erg = 1;

            $werg = 0;
            if ($W1 == $W2) $werg = 0;
            else if ($W1 > $W2) $werg = -1;
            else $werg = 1;

            if ($erg == $werg) return (int) $Ptrend;
            return 0;
        } elseif (strtolower($Art) === 'g') {
            $Punkte = 0;
            if ($W1 == -1 || $W2 == -1) return $Punkte;
            if ($W1 == $Tipp2) $Punkte += (int) $Pok;
            if ($W2 == $Tipp3) $Punkte += (int) $Pok;
            if ($W1 == $Tipp3) $Punkte += (int) $Ptrend;
            if ($W2 == $Tipp2) $Punkte += (int) $Ptrend;
            return $Punkte;
        } elseif (strtolower($Art) === 'v') {
            if ($W1 == -1) return 0;
            if ($W1 == $Tipp1) return (int) $Pok;
            return 0;
        } elseif (strtolower($Art) === 'p') {
            if ($W1 == -1) return 0;
            if ($W1 == $Tipp1) return (int) $Pok;
            return 0;
        }

        return 0;
    }

    private function berechneGruppenPunkte(string $Art, $W1, $W2, $W3, $GRP, $Tipp1, $Tipp2, $Tipp3, $Pok, $Ptrend): int
    {
        $Punkte = 0;

        if (strtolower($Art) === 'g') {
            // W3 spielt bei dir praktisch keine Rolle; wir geben 0 rein (≠ -1)
            if ($W1 == -1 || $W2 == -1 || $W3 == -1) return $Punkte;

            if ($W1 == $Tipp1) $Punkte += (int) $Pok;
            if ($W2 == $Tipp2) $Punkte += (int) $Pok;

            if ($W1 == $Tipp2) $Punkte += (int) $Ptrend;
            if ($W2 == $Tipp1) $Punkte += (int) $Ptrend;
        }

        return $Punkte;
    }

    /* =========================================================
     *  Daten laden
     * ========================================================= */

    private function loadTeilnehmerUndWettenAktuell(Connection $conn, string $Wettbewerb, bool $debug): array
    {
        $sql  = "SELECT";
        $sql .= " t.ID as ID, t.Kurzname as Kurzname, t.Name as Name,";
        $sql .= " wa.W1 as W1, wa.W2 as W2, wa.W3 as W3, wa.Wette as Windex,";
        $sql .= " w.Art as Art, w.Tipp1 as Tipp1, w.ID as WettenId, w.Pok as Pok, w.Ptrend as Ptrend, w.Kommentar as Kommentar,";
        $sql .= " m1.Nation as M1, m1.Flagge as M1Flagge, m1.ID as M1Ind,";
        $sql .= " m2.Nation as M2, m2.Flagge as M2Flagge, m2.ID as M2Ind,";
        $sql .= " m3.Nation as M3, m3.Flagge as M3Flagge, m3.ID as M3Ind,";
        $sql .= " mp.Nation as MPNation, mp.Flagge as MPFlagge, mp.ID as MPInd";
        $sql .= " FROM tl_hy_teilnehmer t";
        $sql .= " LEFT JOIN tl_hy_wetteaktuell wa ON wa.Teilnehmer = t.ID";
        $sql .= " LEFT JOIN tl_hy_wetten w ON wa.Wette = w.ID";
        $sql .= " LEFT JOIN tl_hy_mannschaft m1 ON wa.W1 = m1.ID";
        $sql .= " LEFT JOIN tl_hy_mannschaft m2 ON wa.W2 = m2.ID";
        $sql .= " LEFT JOIN tl_hy_mannschaft m3 ON wa.W3 = m3.ID";
        $sql .= " LEFT JOIN tl_hy_mannschaft mp ON w.Tipp1 = mp.ID";
        $sql .= " WHERE t.Wettbewerb = ?";
        $sql .= " ORDER BY t.Kurzname ASC, w.Art, w.Tipp1 ASC;";

        $stmt = $conn->executeQuery($sql, [$Wettbewerb]);

        $teilnehmerByArt = [];
        $teilnehmerAktWetten = [];

        while (($row = $stmt->fetchAssociative()) !== false) {
            $art = strtolower((string)($row['Art'] ?? ''));
            if ($art !== '') {
                $teilnehmerByArt[$art][] = $row;
            }
            $teilnehmerAktWetten[strtolower((string)($row['Name'] ?? ''))][] = $row;
        }

        return [$teilnehmerByArt, $teilnehmerAktWetten];
    }

    private function loadWetten(Connection $conn, string $Wettbewerb, bool $debug): array
    {
        $sql  = "SELECT w.ID as ID, w.Art as Art, w.Tipp1 as Tipp1, w.Tipp2 as Tipp2, w.Tipp3 as Tipp3, w.Tipp4 as Tipp4,";
        $sql .= " w.Pok as Pok, w.Ptrend as Ptrend, w.Kommentar as Kommentar,";
        $sql .= " mp.Nation as MPNation, mp.Flagge as MPFlagge, mp.ID as MPInd";
        $sql .= " FROM tl_hy_wetten w";
        $sql .= " LEFT JOIN tl_hy_mannschaft mp ON w.Tipp1 = mp.ID";
        $sql .= " WHERE w.Wettbewerb = ?";
        $sql .= " ORDER BY w.Art;";

        $stmt = $conn->executeQuery($sql, [$Wettbewerb]);

        $wetten = [];
        while (($row = $stmt->fetchAssociative()) !== false) {
            $wetten[(string)$row['ID']] = $row;
        }
        return $wetten;
    }

    private function loadRangliste(Connection $conn, string $Wettbewerb): array
    {
        $stmt = $conn->executeQuery(
            "SELECT ID, Kurzname, Name, Punkte FROM tl_hy_teilnehmer WHERE Wettbewerb=? ORDER BY Punkte DESC, Kurzname ASC",
            [$Wettbewerb]
        );

        $out = [];
        while (($r = $stmt->fetchAssociative()) !== false) {
            $out[] = $r;
        }
        return $out;
    }

    private function uniqueTeilnehmerFromAny(array $teilnehmerByArt): array
    {
        $unique = [];
        foreach ($teilnehmerByArt as $rows) {
            foreach ($rows as $tln) {
                $id = (string)($tln['ID'] ?? '');
                if ($id === '') continue;
                if (!isset($unique[$id])) {
                    $unique[$id] = [
                        'ID' => $tln['ID'],
                        'Kurzname' => $tln['Kurzname'] ?? '',
                        'Name' => $tln['Name'] ?? '',
                    ];
                }
            }
        }
        return array_values($unique);
    }

    private function writeFinalPoints(Connection $conn, string $Wettbewerb, array $punkteSumme): void
    {
        foreach ($punkteSumme as $id => $pkt) {
            $conn->executeStatement(
                "UPDATE tl_hy_teilnehmer SET Punkte=? WHERE Wettbewerb=? AND ID=?",
                [(int)$pkt, $Wettbewerb, (int)$id]
            );
        }
    }

    /* =========================================================
     *  SECTIONS (sauberes Datenmodell)
     * ========================================================= */

    private function createSpieleSection(
        Connection $conn,
        string $Wettbewerb,
        string $wherelike,
        array $allTeilnehmer,
        array &$punkteSumme,
        string $title,
        string $nationFilter = ''
    ): array {
        // Spiele laden
        $sql  = "SELECT";
        $sql .= " s.ID as ID, s.Nr as Nr, s.Gruppe as Gruppe,";
        $sql .= " s.M1 as M1Ind, m1.Nation as M1, m1.Flagge as Flagge1,";
        $sql .= " s.M2 as M2Ind, m2.Nation as M2, m2.Flagge as Flagge2,";
        $sql .= " s.Ort as OrtInd, o.Ort as Ort,";
        $sql .= " DATE_FORMAT(s.Datum,'%e.%m.%y') as Datum,";
        $sql .= " DATE_FORMAT(s.Uhrzeit,'%H:%i') as Uhrzeit,";
        $sql .= " s.T1 as T1, s.T2 as T2";
        $sql .= " FROM tl_hy_spiele s";
        $sql .= " LEFT JOIN tl_hy_mannschaft m1 ON s.M1 = m1.ID";
        $sql .= " LEFT JOIN tl_hy_mannschaft m2 ON s.M2 = m2.ID";
        $sql .= " LEFT JOIN tl_hy_orte o ON s.Ort = o.ID";
        $sql .= " WHERE s.Wettbewerb = ? AND LOWER(s.Gruppe) LIKE ?";
        $sql .= " ORDER BY s.Datum ASC, s.Uhrzeit ASC;";

        $stmt = $conn->executeQuery($sql, [$Wettbewerb, $wherelike]);

        $matches = [];
        while (($row = $stmt->fetchAssociative()) !== false) {
            if ($nationFilter !== '') {
                if (strtolower((string)$row['M1']) !== strtolower($nationFilter) && strtolower((string)$row['M2']) !== strtolower($nationFilter)) {
                    continue;
                }
            }
            $matches[] = $row;
        }

        // Keine Spiele? trotzdem Section liefern (Template bleibt stabil)
        if (count($matches) === 0) {
            return [
                'type' => 'spiele',
                'title' => $title,
                'meta' => [
                    'wherelike' => $wherelike,
                    'nationFilter' => $nationFilter,
                    'matchCount' => 0,
                ],
                'rows' => [],
                'debug' => [
                    'matches' => $matches,
                ],
            ];
        }

        // Match-IDs
        $matchIds = array_map(static fn($m) => (int)$m['ID'], $matches);

        // Pok/Ptrend für Spielwetten aus tl_hy_wetten (Art 's')
        $rowp = $conn->executeQuery(
            "SELECT Pok, Ptrend FROM tl_hy_wetten WHERE LOWER(Art)='s' AND Wettbewerb=? LIMIT 1",
            [$Wettbewerb]
        )->fetchAssociative() ?: ['Pok' => 0, 'Ptrend' => 0];

        $Pok = (int)$rowp['Pok'];
        $Ptrend = (int)$rowp['Ptrend'];

        // Prefetch aller Tipps (wetteaktuell) für diese Matches
        // Tipp1 = Spiel-ID
        $in = implode(',', array_fill(0, count($matchIds), '?'));
        $params = array_merge([$Wettbewerb], $matchIds);

        $sqlw =
            "SELECT wa.Teilnehmer, w.Art, w.Tipp1, w.Pok, w.Ptrend, wa.W1, wa.W2
             FROM tl_hy_wetteaktuell wa
             JOIN tl_hy_wetten w ON w.ID = wa.Wette
             WHERE wa.Wettbewerb = ?
               AND LOWER(w.Art)='s'
               AND w.Tipp1 IN ($in)";

        $stmtw = $conn->executeQuery($sqlw, $params);

        $tipps = []; // [TeilnehmerId][SpielId] => row
        while (($r = $stmtw->fetchAssociative()) !== false) {
            $tid = (string)$r['Teilnehmer'];
            $sid = (string)$r['Tipp1'];
            $tipps[$tid][$sid] = $r;
        }

        // Rows je Teilnehmer
        $rows = [];
        foreach ($allTeilnehmer as $tln) {
            $tid = (string)$tln['ID'];
            $cards = [];
            $sum = 0;
            $sumParts = [];

            foreach ($matches as $m) {
                $sid = (string)$m['ID'];

                $tipp = $tipps[$tid][$sid] ?? null;
                $w1 = (int)($tipp['W1'] ?? -1);
                $w2 = (int)($tipp['W2'] ?? -1);

                $points = $this->berechnePunkte(
                    's',
                    $w1,
                    $w2,
                    (int)$m['ID'],
                    (int)($m['T1'] ?? -1),
                    (int)($m['T2'] ?? -1),
                    (int)($tipp['Pok'] ?? $Pok),
                    (int)($tipp['Ptrend'] ?? $Ptrend)
                );

                $sum += $points;
                $sumParts[] = (string)$points;

                $cards[] = [
                    'id' => (int)$m['ID'],
                    'datum' => (string)$m['Datum'],
                    'uhrzeit' => (string)$m['Uhrzeit'],
                    'ort' => (string)($m['Ort'] ?? ''),
                    'm1' => (string)$m['M1'],
                    'm2' => (string)$m['M2'],
                    'flag1' => (string)($m['Flagge1'] ?? ''),
                    'flag2' => (string)($m['Flagge2'] ?? ''),
                    't1' => (string)($m['T1'] ?? ''),
                    't2' => (string)($m['T2'] ?? ''),
                    'wette' => ($w1 === -1 || $w2 === -1) ? '—' : ($w1 . ':' . $w2),
                    'punkte' => $points,
                ];
            }

            // in-memory addieren (einmal pro Section-Teilnehmer)
            $punkteSumme[$tid] = (int)($punkteSumme[$tid] ?? 0) + $sum;

            $rows[] = [
                'id' => (int)$tln['ID'],
                'kurzname' => (string)$tln['Kurzname'],
                'name' => (string)$tln['Name'],
                'cards' => $cards,
                'sum' => implode(' + ', $sumParts) . ' = ' . $sum,
                'sumValue' => $sum,
            ];
        }

        return [
            'type' => 'spiele',
            'title' => $title,
            'meta' => [
                'wherelike' => $wherelike,
                'nationFilter' => $nationFilter,
                'pok' => $Pok,
                'ptrend' => $Ptrend,
                'matchCount' => count($matches),
            ],
            'rows' => $rows,
            'debug' => [
                'matches' => $matches,
                'tippMapInfo' => [
                    'matchIds' => $matchIds,
                    'pok' => $Pok,
                    'ptrend' => $Ptrend,
                ],
            ],
        ];
    }

    private function createGruppenSection(
        Connection $conn,
        string $Wettbewerb,
        array $allTeilnehmer,
        array &$punkteSumme,
        string $title
    ): array {
        // Gruppenstände laden (nur 1-stellige Gruppen A–L)
        $sql =
            "SELECT g.Gruppe as Gruppe, g.Platz as Platz,
                    g.M1 as M1Ind,
                    m.Nation as M1,
                    m.Flagge as Flagge1,
                    m.ID as MID
             FROM tl_hy_gruppen g
             LEFT JOIN tl_hy_mannschaft m ON g.M1 = m.ID
             WHERE g.Wettbewerb = ?
             ORDER BY g.Gruppe ASC, g.Platz ASC";

        $stmt = $conn->executeQuery($sql, [$Wettbewerb]);

        $stand = []; // [Gruppe][Platz] => row
        while (($r = $stmt->fetchAssociative()) !== false) {
            $grp = (string)$r['Gruppe'];
            if (strlen($grp) !== 1) continue; // A–L
            $stand[$grp][(int)$r['Platz']] = $r;
        }

        $gruppenKeys = array_keys($stand);
        sort($gruppenKeys);

        // Pok/Ptrend (Art g)
        $rowp = $conn->executeQuery(
            "SELECT Pok, Ptrend FROM tl_hy_wetten WHERE LOWER(Art)='g' AND Wettbewerb=? LIMIT 1",
            [$Wettbewerb]
        )->fetchAssociative() ?: ['Pok' => 0, 'Ptrend' => 0];

        $Pok = (int)$rowp['Pok'];
        $Ptrend = (int)$rowp['Ptrend'];

        if (count($gruppenKeys) === 0) {
            return [
                'type' => 'gruppen',
                'title' => $title,
                'meta' => ['pok' => $Pok, 'ptrend' => $Ptrend, 'groupCount' => 0],
                'rows' => [],
                'debug' => ['stand' => $stand],
            ];
        }

        // Prefetch Gruppen-Tipps: tl_hy_wetteaktuell join tl_hy_wetten (Art g, Tipp1=Gruppenbuchstabe)
        $in = implode(',', array_fill(0, count($gruppenKeys), '?'));
        $params = array_merge([$Wettbewerb], $gruppenKeys);

        $sqlw =
            "SELECT wa.Teilnehmer, w.Art, w.Tipp1,
                    wa.W1, wa.W2,
                    m1.Nation as TipM1, m1.Flagge as TipFlag1,
                    m2.Nation as TipM2, m2.Flagge as TipFlag2,
                    w.Pok, w.Ptrend
             FROM tl_hy_wetteaktuell wa
             JOIN tl_hy_wetten w ON w.ID = wa.Wette
             LEFT JOIN tl_hy_mannschaft m1 ON wa.W1 = m1.ID
             LEFT JOIN tl_hy_mannschaft m2 ON wa.W2 = m2.ID
             WHERE wa.Wettbewerb = ?
               AND LOWER(w.Art)='g'
               AND w.Tipp1 IN ($in)";

        $stmtw = $conn->executeQuery($sqlw, $params);

        $tipps = []; // [TeilnehmerId][Gruppe] => row
        while (($r = $stmtw->fetchAssociative()) !== false) {
            $tid = (string)$r['Teilnehmer'];
            $grp = (string)$r['Tipp1'];
            $tipps[$tid][$grp] = $r;
        }

        // Rows je Teilnehmer
        $rows = [];
        foreach ($allTeilnehmer as $tln) {
            $tid = (string)$tln['ID'];
            $cards = [];
            $sum = 0;
            $sumParts = [];

            foreach ($gruppenKeys as $grp) {
                $erster = $stand[$grp][1] ?? null;
                $zweiter = $stand[$grp][2] ?? null;

                $tipp = $tipps[$tid][$grp] ?? null;

                $w1 = (int)($tipp['W1'] ?? -1);
                $w2 = (int)($tipp['W2'] ?? -1);

                $points = 0;
                if ($erster && $zweiter) {
                    $points = $this->berechneGruppenPunkte(
                        'g',
                        $w1,
                        $w2,
                        0,
                        $grp,
                        (int)($erster['MID'] ?? -1),
                        (int)($zweiter['MID'] ?? -1),
                        0,
                        (int)($tipp['Pok'] ?? $Pok),
                        (int)($tipp['Ptrend'] ?? $Ptrend)
                    );
                }

                $sum += $points;
                $sumParts[] = (string)$points;

                $cards[] = [
                    'gruppe' => $grp,

                    // Tipp (mit Flaggen) – das wolltest du sichtbar
                    'tipp1' => (string)($tipp['TipM1'] ?? '—'),
                    'tipp2' => (string)($tipp['TipM2'] ?? '—'),
                    'tippFlag1' => (string)($tipp['TipFlag1'] ?? ''),
                    'tippFlag2' => (string)($tipp['TipFlag2'] ?? ''),

                    // Ist (Gruppenstand) – optional, aber hilfreich
                    'ist1' => (string)($erster['M1'] ?? '—'),
                    'ist2' => (string)($zweiter['M1'] ?? '—'),
                    'istFlag1' => (string)($erster['Flagge1'] ?? ''),
                    'istFlag2' => (string)($zweiter['Flagge1'] ?? ''),

                    'punkte' => $points,
                ];
            }

            $punkteSumme[$tid] = (int)($punkteSumme[$tid] ?? 0) + $sum;

            $rows[] = [
                'id' => (int)$tln['ID'],
                'kurzname' => (string)$tln['Kurzname'],
                'name' => (string)$tln['Name'],
                'cards' => $cards,
                'sum' => implode(' + ', $sumParts) . ' = ' . $sum,
                'sumValue' => $sum,
            ];
        }

        return [
            'type' => 'gruppen',
            'title' => $title,
            'meta' => [
                'pok' => $Pok,
                'ptrend' => $Ptrend,
                'groups' => $gruppenKeys,
                'groupCount' => count($gruppenKeys),
            ],
            'rows' => $rows,
            'debug' => [
                'stand' => $stand,
                'groups' => $gruppenKeys,
            ],
        ];
    }

    private function createPuVSection(
        Connection $conn,
        string $Wettbewerb,
        array $wetten,
        array $teilnehmeraktWetten,
        array &$punkteSumme,
        array $selectArray,
        string $title
    ): array {
        // relevante Wetten
        $tippwetten = [];
        foreach ($wetten as $Windex => $row) {
            if (in_array(strtolower((string)$row['Art']), $selectArray, true) || in_array((string)$row['Art'], $selectArray, true)) {
                $tippwetten[$Windex] = $row;
            }
        }

        $rows = [];

        foreach ($teilnehmeraktWetten as $akttlnname => $tlwetten) {
            // Teilnehmer-ID herausfinden (aus erster Zeile)
            $tid = null;
            foreach ($tlwetten as $x) {
                if (!empty($x['ID'])) { $tid = (string)$x['ID']; break; }
            }
            if ($tid === null) continue;

            // aktuelle P/V Wetten des Teilnehmers
            $akttippwetten = [];
            foreach ($tlwetten as $rowaktwett) {
                $Windex = $rowaktwett['Windex'] ?? null;
                if ($Windex !== null && isset($tippwetten[$Windex])) {
                    $akttippwetten[$Windex] = $rowaktwett;
                }
            }
            if (count($akttippwetten) === 0) continue;

            $cards = [];
            $sum = 0;
            $sumParts = [];

            foreach ($tippwetten as $Windex => $wette) {
                $tln = $akttippwetten[$Windex];

                $points = $this->berechnePunkte(
                    (string)($tln['Art'] ?? ''),
                    (int)($tln['W1'] ?? -1),
                    (int)($tln['W2'] ?? -1),
                    (int)($wette['Tipp1'] ?? -1),
                    (int)($wette['Tipp2'] ?? -1),
                    (int)($wette['Tipp3'] ?? -1),
                    (int)($wette['Pok'] ?? 0),
                    (int)($wette['Ptrend'] ?? 0)
                );

                $sum += $points;
                $sumParts[] = (string)$points;

                $cards[] = [
                    'wetteId' => (string)$Windex,
                    'kommentar' => (string)($wette['Kommentar'] ?? ''),
                    'gewettet' => (string)(($tln['W1'] ?? -1) . ' : ' . ($tln['M1'] ?? '')),
                    'punkte' => $points,
                ];
            }

            $punkteSumme[$tid] = (int)($punkteSumme[$tid] ?? 0) + $sum;

            $rows[] = [
                'id' => (int)$tid,
                'name' => (string)$akttlnname,
                'cards' => $cards,
                'sum' => implode(' + ', $sumParts) . ' = ' . $sum,
                'sumValue' => $sum,
            ];
        }

        return [
            'type' => 'puv',
            'title' => $title,
            'meta' => [
                'countWetten' => count($tippwetten),
            ],
            'rows' => $rows,
            'debug' => [
                'tippwetten' => $tippwetten,
            ],
            'infotext' => "1: Meister\n2: Ausscheiden in Finale\n4: Ausscheiden in Halbfinale\n8: Ausscheiden in Viertelfinale\n16: Ausscheiden in Achtelfinale\n32: Ausscheiden in Sechzehntelfinale\n64: Ausscheiden in Gruppenspielen\n",
        ];
    }
}
