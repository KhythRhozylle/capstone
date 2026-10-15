<?php

namespace App\Controller;

use App\Repository\DocumentRequestRepository;
use App\Service\QrCodeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class VerifyController extends AbstractController
{
    #[Route('/verify/{token}', name: 'app_verify_document', methods: ['GET'])]
    public function document(string $token, DocumentRequestRepository $documents): Response
    {
        $document = $documents->findOneByToken($token);
        if (!$document) {
            return $this->render('verify/document.html.twig', [
                'valid' => false,
                'document' => null,
            ]);
        }

        return $this->render('verify/document.html.twig', [
            'valid' => \in_array($document->getStatus()->value, ['ready', 'released'], true),
            'document' => $document,
        ]);
    }

    #[Route('/verify/{token}/qr.png', name: 'app_verify_qr', methods: ['GET'])]
    public function qr(string $token, DocumentRequestRepository $documents, QrCodeService $qr, UrlGeneratorInterface $urls): Response
    {
        $document = $documents->findOneByToken($token);
        if (!$document) {
            throw $this->createNotFoundException();
        }

        $verifyUrl = $urls->generate('app_verify_document', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        $result = $qr->png($verifyUrl);

        return new Response($result->getString(), Response::HTTP_OK, ['Content-Type' => $result->getMimeType()]);
    }
}
