<?php

declare(strict_types=1);

require __DIR__.'/compatibility.php';

use PBDKN\FussballBundle\Admin\AdminService;
use PBDKN\FussballBundle\Admin\AdminView;
use PBDKN\FussballBundle\Controller\Ajax\FussballRequestClass;
use PBDKN\FussballBundle\Migration\ParticipantPasswordMigration;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;

// Continue using only the isolated in-memory database from compatibility.php.
$connection->executeStatement('ALTER TABLE tl_hy_config ADD COLUMN tstamp INTEGER DEFAULT 0');
foreach ($GLOBALS['TL_DCA'] as $table => $dca) {
    if (!str_starts_with($table, 'tl_hy_') || $table === 'tl_hy_config') {
        continue;
    }
    $columns = [];
    foreach ($dca['fields'] as $name => $field) {
        $columns[] = $name.' '.(strtolower($name) === 'id' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : "TEXT DEFAULT ''");
    }
    $connection->executeStatement('CREATE TABLE '.$table.' ('.implode(', ', $columns).')');
}
$session = new Session(new MockArraySessionStorage());
$stack->getCurrentRequest()->setSession($session);
$token = $csrfTokens->getToken('fussball-test')->getValue();
$ajax = $container->get(FussballRequestClass::class);
$service = $container->get(AdminService::class);
$view = $container->get(AdminView::class);

$send = static function (string $entity, array $data, int $expected = 200, string $method = 'POST', array $headers = [], bool $includeToken = true, string $query = '') use ($ajax, $token, $stack, $session): array {
    $operation = $entity === 'tip' ? 'storeteilnehmerwette' : 'bearbeite'.$entity;
    if ($includeToken && !isset($data['REQUEST_TOKEN'])) {
        $data['REQUEST_TOKEN'] = $token;
    }
    $request = Request::create('/contao/fussball/'.$operation.$query, $method, $data);
    $request->attributes->set('_scope', 'backend');
    $request->setSession($session);
    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }
    $stack->pop();
    $stack->push($request);
    $response = $ajax->$operation($request);
    check($response->getStatusCode() === $expected, $operation.' expected '.$expected.', got '.$response->getStatusCode().': '.$response->getContent());
    check(str_contains($response->headers->get('Cache-Control'), 'no-store'), 'Sensitive response must not be cached.');
    $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    check(array_keys($body) === ['data', 'error'], 'Debug data leaked in response.');
    return $body;
};

$placeData = ['aktion' => 'n', 'ort' => 'Stadion', 'beschreibung' => 'Test'];
$testTokenChecker->authenticated = false;
$send('ort', $placeData, 403);
$response = $ajax->anzeigeteilnehmer(Request::create('/fussball/anzeigeteilnehmer/u/1'), 'u', 1);
check($response->getStatusCode() === 403, 'Anonymous read allowed.');
$notice = $container->get(PBDKN\FussballBundle\Controller\ContentElement\VW\VWTeilnehmerController::class)(Request::create('/verwaltung/teilnehmer.html'), $model, 'main');
check($notice->getStatusCode() === 200 && str_contains($notice->getContent(), 'Fußball-Verwaltung im Backend'), 'Missing legacy backend link.');
check(str_contains($notice->getContent(), 'href="/contao?do=pbd_fussball_teilnehmer"'), 'Backend module link missing.');
check(!str_contains($notice->getContent(), 'data-fussball-admin') && !str_contains($notice->getContent(), '<table'), 'Management data rendered for anonymous visitor.');
check(str_contains($notice->headers->get('Cache-Control'), 'no-store'), 'Login notice must not be cached.');
$testTokenChecker->authenticated = true;
foreach ([['admin' => '0'], ['disable' => '1'], ['start' => time() + 3600], ['stop' => time() - 1]] as $change) {
    $connection->update('tl_user', $change, ['username' => 'compat-admin']);
    $send('ort', $placeData, 403);
    $connection->update('tl_user', ['admin' => '1', 'disable' => '', 'start' => 0, 'stop' => 0], ['username' => 'compat-admin']);
}
check((int) $connection->fetchOne('SELECT COUNT(*) FROM tl_hy_orte') === 0, 'Denied requests changed data.');
echo "OK: anonymous/member/non-admin/revoked account requests denied, management elements private\n";

$send('ort', $placeData, 405, 'GET');
$send('ort', $placeData, 403, 'POST', [], false);
$send('ort', $placeData + ['REQUEST_TOKEN' => 'wrong'], 403);
$send('ort', $placeData, 403, 'POST', ['Origin' => 'https://attacker.example']);
$send('ort', $placeData, 403, 'POST', ['Sec-Fetch-Site' => 'cross-site']);
$send('ort', $placeData, 400, 'POST', [], true, '?pwd=never-in-url');
$otherSession = new Session(new MockArraySessionStorage());
$otherRequest = Request::create('/');
$otherRequest->setSession($otherSession);
$otherStack = new Symfony\Component\HttpFoundation\RequestStack();
$otherStack->push($otherRequest);
$otherCsrf = new Symfony\Component\Security\Csrf\CsrfTokenManager(null, new Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage($otherStack));
$send('ort', $placeData + ['REQUEST_TOKEN' => $otherCsrf->getToken('fussball-test')->getValue()], 403);
check((int) $connection->fetchOne('SELECT COUNT(*) FROM tl_hy_orte') === 0, 'CSRF or GET request changed data.');
$context = new RequestContext();
$context->setMethod('GET');
try {
    (new UrlMatcher($routes, $context))->match('/contao/fussball/bearbeiteort');
    throw new RuntimeException('Router accepted write via GET.');
} catch (Symfony\Component\Routing\Exception\MethodNotAllowedException $e) {
}
foreach (['/fussball/bearbeiteort', '/fussball/anzeigeort/n/-1', '/fussball/bearbeiteteilnehmer/d/1', '/fussball/bearbeiteteilnehmer/n/1/a/b/c/secret', '/fussball/testAjax/1'] as $oldPath) {
    try {
        (new UrlMatcher($routes, $context))->match($oldPath);
        throw new RuntimeException('Legacy unsafe route still exists.');
    } catch (Symfony\Component\Routing\Exception\ResourceNotFoundException $e) {
    }
}
echo "OK: POST-only writes, CSRF session binding, origin checks and legacy route removal\n";

$payload = "O'Reilly <img src=x onerror=alert(1)> ' OR 1=1 --";
$send('ort', ['aktion' => 'n', 'ort' => $payload, 'beschreibung' => 'A & B']);
$place = (int) $connection->lastInsertId();
check($connection->fetchOne('SELECT Ort FROM tl_hy_orte WHERE ID = ?', [$place]) === $payload, 'Input was not stored as literal SQL data.');
$html = $view->form('ort', 'u', $place);
check(!str_contains($html, '<img src=x') && str_contains($html, '&lt;img'), 'Edit form failed to escape stored HTML.');
$html = $view->render('ort', Request::create('/'));
check(!str_contains($html, '<img src=x') && str_contains($html, '&lt;img'), 'Management table failed to escape stored HTML.');
$send('ort', ['aktion' => 'n', 'ort' => ['injected'], 'beschreibung' => 'x'], 400);
$send('ort', ['aktion' => 'u', 'ID' => '1 OR 1=1', 'ort' => 'x', 'beschreibung' => 'x'], 400);
$send('ort', ['aktion' => 'd', 'ID' => ['1']], 400);
echo "OK: SQL payload stored literally, HTML escaped, malformed inputs rejected\n";

$send('nation', ['aktion' => 'n', 'Nation' => 'Deutschland', 'Type' => 'n', 'Alfa2' => 'de', 'Alfa3' => 'deu', 'Domain' => 'de', 'Image' => 'de.gif']);
$send('nation', ['aktion' => 'n', 'Nation' => 'Ungültig', 'Type' => 'n', 'Image' => '../../secret.gif'], 400);
$send('mannschaft', ['aktion' => 'n', 'name' => 'Heim', 'nation' => 'Deutschland', 'Gruppe' => 'A']);
$home = (int) $connection->lastInsertId();
$send('mannschaft', ['aktion' => 'n', 'name' => 'Gast', 'nation' => 'Deutschland', 'Gruppe' => 'A']);
$away = (int) $connection->lastInsertId();
$send('spiel', ['aktion' => 'n', 'Nr' => '1', 'Gruppe' => 'A', 'M1' => $home, 'M2' => $away, 'Ort' => $place, 'Datum' => '2026-06-11', 'Uhrzeit' => '18:00', 'T1' => '2', 'T2' => '1']);
$game = (int) $connection->lastInsertId();
$send('spiel', ['aktion' => 'n', 'Nr' => 2, 'Gruppe' => 'A', 'M1' => $home, 'M2' => $away, 'Ort' => $place, 'Datum' => '2026-02-30', 'Uhrzeit' => '18:00', 'T1' => -1, 'T2' => -1], 400);
$send('gruppe', ['aktion' => 'a']);
check((int) $connection->fetchOne('SELECT Punkte FROM tl_hy_gruppen WHERE M1 = ?', [$home]) === 3, 'Group rebuild changed winner points.');
check((int) $connection->fetchOne('SELECT Platz FROM tl_hy_gruppen WHERE M1 = ?', [$home]) === 1, 'Group rebuild ranking incorrect.');
$send('wette', ['aktion' => 'n', 'Art' => 's', 'Kommentar' => $payload, 'Pok' => 5, 'Ptrend' => 2, 'Tipp1' => $game, 'Tipp2' => -1, 'Tipp3' => -1, 'Tipp4' => -1]);
$bet = (int) $connection->lastInsertId();
$send('wette', ['aktion' => 'u']);
check((int) $connection->fetchOne('SELECT Tipp2 FROM tl_hy_wetten WHERE ID = ?', [$bet]) === 2, 'Bet result update failed.');

$secret = 'test-only-long-password';
$participantData = ['aktion' => 'n', 'Name' => $payload, 'Kurzname' => "O'Brien", 'Email' => 'test@example.org', 'pwd' => $secret, 'Erst' => '1'];
$send('teilnehmer', $participantData);
$participant = (int) $connection->fetchOne('SELECT ID FROM tl_hy_teilnehmer WHERE Kurzname = ?', ["O'Brien"]);
$hash = $connection->fetchOne('SELECT pwd FROM tl_hy_teilnehmer WHERE ID = ?', [$participant]);
check($hash !== $secret && password_verify($secret, $hash), 'New password was not hashed.');
$form = $view->form('teilnehmer', 'u', $participant);
$table = $view->render('teilnehmer', Request::create('/'));
check(!str_contains($form.$table, $secret) && !str_contains($form.$table, $hash), 'Password/hash disclosed in form or listing.');
check(str_contains($form, 'type="password"') && str_contains($form, 'name="pwd" value=""'), 'Password field must be blank and masked.');
$send('teilnehmer', array_replace($participantData, ['aktion' => 'u', 'ID' => $participant, 'pwd' => '']));
check($connection->fetchOne('SELECT pwd FROM tl_hy_teilnehmer WHERE ID = ?', [$participant]) === $hash, 'Blank edit cleared password.');
$send('teilnehmer', array_replace($participantData, ['aktion' => 'u', 'ID' => $participant, 'pwd' => 'short']), 400);
$tip = (int) $connection->fetchOne('SELECT ID FROM tl_hy_wetteaktuell WHERE Teilnehmer = ? AND Wette = ?', [$participant, $bet]);
$send('tip', ['aktion' => 's', 'Idwettaktuell' => $tip, 'W1' => '2', 'W2' => '1', 'W3' => '-1']);
check((int) $connection->fetchOne('SELECT W1 FROM tl_hy_wetteaktuell WHERE ID = ?', [$tip]) === 2, 'Tip update failed.');
$send('tip', ['aktion' => 's', 'Idwettaktuell' => $tip, 'W1' => '1 OR 1=1', 'W2' => 1], 400);
$send('tip', ['aktion' => 's', 'Idwettaktuell' => $tip, 'W1' => '-2', 'W2' => 1], 400);
$tipsHtml = $view->participantBets($participant);
check(!str_contains($tipsHtml, '<img src=x') && str_contains($tipsHtml, '&lt;img'), 'Participant tip form failed to escape labels.');
echo "OK: real CRUD/rebuild/result/tip workflows, hashed passwords and blank-password preservation\n";

$connection->insert('tl_hy_config', ['Name' => 'Wettbewerb', 'aktuell' => 0, 'value1' => 'OTHER']);
$otherCompetition = (int) $connection->lastInsertId();
$connection->insert('tl_hy_orte', ['Wettbewerb' => 'OTHER', 'Ort' => 'Fremd']);
$foreignPlace = (int) $connection->lastInsertId();
$send('ort', ['aktion' => 'u', 'ID' => $foreignPlace, 'ort' => 'Hacked'], 404);
$send('ort', ['aktion' => 'd', 'ID' => $foreignPlace], 404);
$send('spiel', ['aktion' => 'n', 'Nr' => 2, 'Gruppe' => 'A', 'M1' => $home, 'M2' => $away, 'Ort' => $foreignPlace, 'Datum' => '2026-06-11', 'Uhrzeit' => '18:00', 'T1' => -1, 'T2' => -1], 404);
$connection->insert('tl_hy_teilnehmer', ['Wettbewerb' => 'OTHER', 'Name' => 'Fremd', 'Kurzname' => 'Fremd']);
$foreignParticipant = (int) $connection->lastInsertId();
$connection->insert('tl_hy_wetten', ['Wettbewerb' => 'OTHER', 'Kommentar' => 'Fremd', 'Art' => 's', 'Tipp1' => $game]);
$foreignBet = (int) $connection->lastInsertId();
$connection->insert('tl_hy_wetteaktuell', ['Wettbewerb' => 'OTHER', 'Teilnehmer' => $foreignParticipant, 'Wette' => $foreignBet, 'W1' => '-1']);
$foreignTip = (int) $connection->lastInsertId();
$send('tip', ['aktion' => 's', 'Idwettaktuell' => $foreignTip, 'W1' => 99], 404);
check($connection->fetchOne('SELECT W1 FROM tl_hy_wetteaktuell WHERE ID = ?', [$foreignTip]) === '-1', 'Foreign tip changed.');
check($connection->fetchOne('SELECT Ort FROM tl_hy_orte WHERE ID = ?', [$foreignPlace]) === 'Fremd', 'Foreign record changed.');
$connection->insert('tl_hy_wetteaktuell', ['Wettbewerb' => 'WM2026', 'Teilnehmer' => $foreignParticipant, 'Wette' => $bet]);
$orphanTip = (int) $connection->lastInsertId();
$send('tip', ['aktion' => 's', 'Idwettaktuell' => $orphanTip, 'W1' => 1], 404);
echo "OK: competition/participant/bet association checked, foreign and orphan references rejected\n";

// Rollback all steps if creating dependent tip rows fails; expose no DB exception.
$connection->executeStatement("CREATE TRIGGER reject_tips BEFORE INSERT ON tl_hy_wetteaktuell BEGIN SELECT RAISE(ABORT, 'private SQL diagnostic'); END");
$error = $send('teilnehmer', array_replace($participantData, ['Kurzname' => 'rollback-user', 'pwd' => '']), 500);
check(!$connection->fetchOne('SELECT ID FROM tl_hy_teilnehmer WHERE Kurzname = ?', ['rollback-user']), 'Failed multi-table write was not rolled back.');
check(!str_contains(json_encode($error), 'private SQL diagnostic'), 'Database details disclosed.');
$connection->executeStatement('DROP TRIGGER reject_tips');

$connection->update('tl_hy_teilnehmer', ['pwd' => 'legacy-plaintext'], ['ID' => $foreignParticipant]);
$migration = $container->get(ParticipantPasswordMigration::class);
check($migration->shouldRun(), 'Legacy password migration not detected.');
check($migration->run()->isSuccessful(), 'Password migration failed.');
$migratedHash = $connection->fetchOne('SELECT pwd FROM tl_hy_teilnehmer WHERE ID = ?', [$foreignParticipant]);
check(password_verify('legacy-plaintext', $migratedHash), 'Legacy password was not migrated to a usable hash.');
check($connection->fetchOne('SELECT pwd FROM tl_hy_teilnehmer WHERE ID = ?', [$participant]) === $hash, 'Existing password hash was hashed twice.');
check(!$migration->shouldRun(), 'Password migration is not idempotent.');
echo "OK: transactional rollback, sanitized errors, idempotent legacy-password migration\n";

$util = new PBDKN\FussballBundle\Util\FussballUtil();
check($util->getImagePath('de.gif') !== '', 'Valid flag path rejected.');
check($util->getImagePath("x' onerror='alert(1).gif") === '', 'Unsafe flag attribute allowed.');
check($util->getImagePath('../../secret.gif') === '', 'Flag traversal allowed.');
$pointController = $container->get(PBDKN\FussballBundle\Controller\ContentElement\FE\FeEndstandController::class);
$rankingMethod = new ReflectionMethod($pointController, 'loadRangliste');
$rankingMethod->setAccessible(true);
$connection->update('tl_hy_teilnehmer', ['Punkte' => 123], ['ID' => $participant]);
$ranking = $rankingMethod->invoke($pointController, $connection, 'WM2026', [(string) $participant => 5]);
check($ranking[0]['Punkte'] === 5, 'Public ranking no longer calculates points.');
check($connection->fetchOne('SELECT Punkte FROM tl_hy_teilnehmer WHERE ID = ?', [$participant]) === '123', 'Public ranking changed stored data.');

// Inject a stored XSS probe into every public text/detail path used by these views.
$publicView = new class {
    public $fussballUtil;
    public $wettbewerb;
    public $datumStart = '';
    public $datumEnde = '';
    public $wetten;
    public $gruppenListen;
    public $teilnehmer;
    public $summe;
    public $mannschaften;
    public $spiele;
    public $data;

    public function render(string $file): string
    {
        ob_start();
        include $file;
        return ob_get_clean();
    }
};
$publicView->fussballUtil = $util;
$publicView->wettbewerb = $payload;
$publicView->wetten = [['row' => ['Kommentar' => $payload, 'Art' => 'v', 'Pok' => 3, 'Ptrend' => 1, 'Tipp1' => 1, 'Tipp2' => -1, 'Tipp3' => -1, 'Tipp4' => -1], 'details' => []]];
$html = $publicView->render($root.'/src/Resources/contao/templates/content_element/ce_fe_fussball_wetten.html5');
check(!str_contains($html, '<img src=x') && str_contains($html, '&lt;img'), 'Public bet output vulnerable to stored XSS.');
$publicView->gruppenListen = [[['Gruppe' => $payload, 'M1Name' => $payload, 'Flagge1' => 'de.gif', 'Platz' => 1, 'Spiele' => 1, 'Sieg' => 1, 'Unentschieden' => 0, 'Niederlage' => 0, 'Tore' => 2, 'Gegentore' => 1, 'Differenz' => 1, 'Punkte' => 3]]];
$html = $publicView->render($root.'/src/Resources/contao/templates/content_element/ce_fe_fussball_gruppen.html5');
check(!str_contains($html, '<img src=x'), 'Public group output vulnerable to stored XSS.');
$publicView->teilnehmer = [['ID' => $participant, 'Name' => $payload]];
$publicView->summe = [$participant => 5];
$publicView->mannschaften = [$home => ['Name' => $payload, 'Flagge' => 'de.gif'], $away => ['Name' => $payload, 'Flagge' => 'de.gif']];
$publicView->spiele = [$game => ['M1' => $home, 'M2' => $away, 'T1' => 2, 'T2' => 1]];
$publicView->wetten = [$participant => [['Art' => 'S', 'Kommentar' => $payload, 'Punkte' => 5, 'Tipp1' => $game, 'W1' => 2, 'W2' => 1]]];
$html = $publicView->render($root.'/src/Resources/contao/templates/content_element/ce_fe_fussball_punkte.html5');
check(!str_contains($html, '<img src=x'), 'Public points detail vulnerable to stored XSS.');
echo "OK: flag paths, read-only public ranking and stored-XSS probes in public templates\n";

echo "All isolated security regression checks passed.\n";
