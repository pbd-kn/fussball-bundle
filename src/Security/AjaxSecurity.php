<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Security;

use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class AjaxSecurity
{
    private TokenChecker $tokenChecker;
    private Connection $connection;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private string $tokenName;
    private ScopeMatcher $scopeMatcher;

    public function __construct(TokenChecker $tokenChecker, Connection $connection, CsrfTokenManagerInterface $csrfTokenManager, string $tokenName, ScopeMatcher $scopeMatcher)
    {
        $this->tokenChecker = $tokenChecker;
        $this->connection = $connection;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->tokenName = $tokenName;
        $this->scopeMatcher = $scopeMatcher;
    }

    public function assertAdministrator(): void
    {
        // A frontend member or a preview link does not grant management privileges.
        if (!$this->tokenChecker->hasBackendUser() || null === ($username = $this->tokenChecker->getBackendUsername())) {
            throw new AccessDeniedHttpException('Bitte als Contao-Backend-Administrator anmelden.');
        }

        // Re-check the current account, including revocation of an existing session.
        $user = $this->connection->fetchAssociative('SELECT admin, disable, start, stop FROM tl_user WHERE username = ?', [$username]);
        $now = time();
        if (!$user || (string) $user['admin'] !== '1' || !empty($user['disable'])
            || (!empty($user['start']) && (int) $user['start'] > $now)
            || (!empty($user['stop']) && (int) $user['stop'] <= $now)) {
            throw new AccessDeniedHttpException('Für diese Verwaltung sind Administratorrechte erforderlich.');
        }
    }

    public function assertRequest(Request $request, bool $write): void
    {
        if (!$request->isMethod($write ? 'POST' : 'GET')) {
            throw new MethodNotAllowedHttpException([$write ? 'POST' : 'GET']);
        }
        if (!$this->scopeMatcher->isBackendRequest($request)) {
            throw new AccessDeniedHttpException('Die Fußball-Verwaltung ist nur im Backend verfügbar.');
        }
        $this->assertAdministrator();
        if (!$write) {
            return;
        }

        // Never accept passwords, tokens or write parameters in URLs.
        if ($request->query->count() !== 0 || strlen($request->getContent()) > 65536) {
            throw new BadRequestHttpException('Schreibdaten müssen im POST-Formular übertragen werden.');
        }
        $origin = $request->headers->get('Origin');
        if ($origin !== null && strtolower(rtrim($origin, '/')) !== strtolower($request->getSchemeAndHttpHost())) {
            throw new AccessDeniedHttpException('Die Anfrage stammt nicht von dieser Website.');
        }
        if ($request->headers->get('Sec-Fetch-Site') === 'cross-site') {
            throw new AccessDeniedHttpException('Websiteübergreifende Anfragen sind nicht erlaubt.');
        }
        $body = $request->request->all();
        $token = $body['REQUEST_TOKEN'] ?? null;
        if (!is_string($token) || $token === '' || !$this->csrfTokenManager->isTokenValid(new CsrfToken($this->tokenName, $token))) {
            throw new AccessDeniedHttpException('Sicherheitstoken ungültig. Bitte die Seite neu laden.');
        }
    }

    public function getToken(): string
    {
        $this->assertAdministrator();
        return $this->csrfTokenManager->getToken($this->tokenName)->getValue();
    }

    public static function privateResponse(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setVary('Cookie', false);
        return $response;
    }
}
