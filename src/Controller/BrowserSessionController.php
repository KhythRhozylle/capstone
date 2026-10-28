<?php

namespace App\Controller;

use App\Dto\BrowserSessionContinuation;
use App\Service\BrowserSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class BrowserSessionController extends AbstractController
{
    #[Route('/session/continue', name: 'app_session_continue', methods: ['POST'], format: 'json')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function continueSession(Request $request, BrowserSession $sessions, #[MapRequestPayload] BrowserSessionContinuation $continuation): JsonResponse
    {
        return $this->json(['url' => $sessions->continuationUrl($request, $continuation->target)]);
    }
}
