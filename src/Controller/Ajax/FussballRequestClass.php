<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Controller\Ajax;

use PBDKN\FussballBundle\Admin\AdminService;
use PBDKN\FussballBundle\Admin\AdminView;
use PBDKN\FussballBundle\Security\AjaxSecurity;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('%contao.backend.route_prefix%/fussball', defaults: ['_scope' => 'backend', '_store_referrer' => false])]
class FussballRequestClass extends AbstractController
{
    private AdminService $service;
    private AdminView $view;
    private AjaxSecurity $security;
    private LoggerInterface $logger;

    public function __construct(AdminService $service, AdminView $view, AjaxSecurity $security, LoggerInterface $logger)
    {
        $this->service = $service;
        $this->view = $view;
        $this->security = $security;
        $this->logger = $logger;
    }

    private function handle(Request $request, bool $write, callable $operation): JsonResponse
    {
        try {
            $this->security->assertRequest($request, $write);
            $response = new JsonResponse(['data' => $operation(), 'error' => '']);
        } catch (\InvalidArgumentException $e) {
            $response = new JsonResponse(['data' => '', 'error' => $e->getMessage()], 400);
        } catch (HttpExceptionInterface $e) {
            $response = new JsonResponse(['data' => '', 'error' => $e->getMessage()], $e->getStatusCode(), $e->getHeaders());
        } catch (\Throwable $e) {
            // Do not expose SQL, filesystem paths, form values or credentials.
            $this->logger->error('Fußball-Verwaltungsanfrage fehlgeschlagen.', ['exception_class' => get_class($e)]);
            $response = new JsonResponse(['data' => '', 'error' => 'Die Anfrage konnte nicht verarbeitet werden.'], 500);
        }
        return AjaxSecurity::privateResponse($response);
    }

    #[Route('/anzeigewettbewerb/{aktion}/{ID}', name: 'FussballRequestClass_anzeigewettbewerb', defaults: ['_scope' => 'backend'], requirements: ['aktion' => 'n|u|b', 'ID' => '-?\\d{1,10}'], methods: ['GET'])]
    public function anzeigewettbewerb(Request $request, string $aktion, int $ID): JsonResponse
    {
        return $this->handle($request, false, fn (): string => $this->view->form('wettbewerb', $aktion, $ID));
    }

    #[Route('/bearbeitewettbewerb', name: 'FussballRequestClass_bearbeitewettbewerb', defaults: ['_scope' => 'backend'], methods: ['POST'])]
    public function bearbeitewettbewerb(Request $request): JsonResponse
    {
        return $this->handle($request, true, fn (): string => $this->service->save('wettbewerb', $request->request->all()));
    }

    #[Route('/anzeigemannschaft/{aktion}/{ID}', name: 'FussballRequestClass_anzeigemannschaft', defaults: ['_scope' => 'backend'], requirements: ['aktion' => 'n|u|b', 'ID' => '-?\\d{1,10}'], methods: ['GET'])]
    public function anzeigemannschaft(Request $request, string $aktion, int $ID): JsonResponse
    {
        return $this->handle($request, false, fn (): string => $this->view->form('mannschaft', $aktion, $ID));
    }

    #[Route('/bearbeitemannschaft', name: 'FussballRequestClass_bearbeitemannschaft', defaults: ['_scope' => 'backend'], methods: ['POST'])]
    public function bearbeitemannschaft(Request $request): JsonResponse
    {
        return $this->handle($request, true, fn (): string => $this->service->save('mannschaft', $request->request->all()));
    }

    #[Route('/anzeigenation/{aktion}/{ID}', name: 'FussballRequestClass_anzeigenation', defaults: ['_scope' => 'backend'], requirements: ['aktion' => 'n|u|b', 'ID' => '-?\\d{1,10}'], methods: ['GET'])]
    public function anzeigenation(Request $request, string $aktion, int $ID): JsonResponse
    {
        return $this->handle($request, false, fn (): string => $this->view->form('nation', $aktion, $ID));
    }

    #[Route('/bearbeitenation', name: 'FussballRequestClass_bearbeitenation', defaults: ['_scope' => 'backend'], methods: ['POST'])]
    public function bearbeitenation(Request $request): JsonResponse
    {
        return $this->handle($request, true, fn (): string => $this->service->save('nation', $request->request->all()));
    }

    #[Route('/anzeigeort/{aktion}/{ID}', name: 'FussballRequestClass_anzeigeort', defaults: ['_scope' => 'backend'], requirements: ['aktion' => 'n|u|b', 'ID' => '-?\\d{1,10}'], methods: ['GET'])]
    public function anzeigeort(Request $request, string $aktion, int $ID): JsonResponse
    {
        return $this->handle($request, false, fn (): string => $this->view->form('ort', $aktion, $ID));
    }

    #[Route('/bearbeiteort', name: 'FussballRequestClass_bearbeiteort', defaults: ['_scope' => 'backend'], methods: ['POST'])]
    public function bearbeiteort(Request $request): JsonResponse
    {
        return $this->handle($request, true, fn (): string => $this->service->save('ort', $request->request->all()));
    }

    #[Route('/anzeigespiel/{aktion}/{ID}', name: 'FussballRequestClass_anzeigespiel', defaults: ['_scope' => 'backend'], requirements: ['aktion' => 'n|u|b', 'ID' => '-?\\d{1,10}'], methods: ['GET'])]
    public function anzeigespiel(Request $request, string $aktion, int $ID): JsonResponse
    {
        return $this->handle($request, false, fn (): string => $this->view->form('spiel', $aktion, $ID));
    }

    #[Route('/bearbeitespiel', name: 'FussballRequestClass_bearbeitespiel', defaults: ['_scope' => 'backend'], methods: ['POST'])]
    public function bearbeitespiel(Request $request): JsonResponse
    {
        return $this->handle($request, true, fn (): string => $this->service->save('spiel', $request->request->all()));
    }

    #[Route('/anzeigegruppe/{aktion}/{ID}', name: 'FussballRequestClass_anzeigegruppe', defaults: ['_scope' => 'backend'], requirements: ['aktion' => 'n|u|b', 'ID' => '-?\\d{1,10}'], methods: ['GET'])]
    public function anzeigegruppe(Request $request, string $aktion, int $ID): JsonResponse
    {
        return $this->handle($request, false, fn (): string => $this->view->form('gruppe', $aktion, $ID));
    }

    #[Route('/bearbeitegruppe', name: 'FussballRequestClass_bearbeitegruppe', defaults: ['_scope' => 'backend'], methods: ['POST'])]
    public function bearbeitegruppe(Request $request): JsonResponse
    {
        return $this->handle($request, true, fn (): string => $this->service->save('gruppe', $request->request->all()));
    }

    #[Route('/anzeigewette/{aktion}/{ID}/{Type}', name: 'FussballRequestClass_anzeigewette', defaults: ['_scope' => 'backend'], requirements: ['aktion' => 'n|u|b', 'ID' => '-?\\d{1,10}'], methods: ['GET'])]
    public function anzeigewette(Request $request, string $aktion, int $ID, string $Type = 's'): JsonResponse
    {
        return $this->handle($request, false, fn (): string => $this->view->form('wette', $aktion, $ID, $Type));
    }

    #[Route('/bearbeitewette', name: 'FussballRequestClass_bearbeitewette', defaults: ['_scope' => 'backend'], methods: ['POST'])]
    public function bearbeitewette(Request $request): JsonResponse
    {
        return $this->handle($request, true, fn (): string => $this->service->save('wette', $request->request->all()));
    }

    #[Route('/anzeigeteilnehmer/{aktion}/{ID}', name: 'FussballRequestClass_anzeigeteilnehmer', defaults: ['_scope' => 'backend'], requirements: ['aktion' => 'n|u|b', 'ID' => '-?\\d{1,10}'], methods: ['GET'])]
    public function anzeigeteilnehmer(Request $request, string $aktion, int $ID): JsonResponse
    {
        return $this->handle($request, false, fn (): string => $this->view->form('teilnehmer', $aktion, $ID));
    }

    #[Route('/bearbeiteteilnehmer', name: 'FussballRequestClass_bearbeiteteilnehmer', defaults: ['_scope' => 'backend'], methods: ['POST'])]
    public function bearbeiteteilnehmer(Request $request): JsonResponse
    {
        return $this->handle($request, true, fn (): string => $this->service->save('teilnehmer', $request->request->all()));
    }

    #[Route('/teilnehmerwetten/{ID}', name: 'FussballRequestClass_teilnehmerwetten', defaults: ['_scope' => 'backend'], requirements: ['ID' => '\\d{1,10}'], methods: ['GET'])]
    public function teilnehmerwetten(Request $request, int $ID): JsonResponse
    {
        return $this->handle($request, false, fn (): string => $this->view->participantBets($ID));
    }

    #[Route('/storeteilnehmerwette', name: 'FussballRequestClass_storeteilnehmerwette', defaults: ['_scope' => 'backend'], methods: ['POST'])]
    public function storeteilnehmerwette(Request $request): JsonResponse
    {
        return $this->handle($request, true, fn (): string => $this->service->storeParticipantBet($request->request->all()));
    }
}
