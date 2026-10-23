<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Public pages for the university publications that post announcements. */
class AnnouncementChannelController extends AbstractController
{
    private const CHANNELS = [
        'tn' => [
            'name' => 'The NORSUnian',
            'handle' => '@The NORSUnian',
            'description' => 'The NORSUnian (TN) is the official weekly student publication of Negros Oriental State University.',
        ],
        'hadurawan' => [
            'name' => 'HADURAWAN',
            'handle' => '@HADURAWAN',
            'description' => 'HADURAWAN NORSU showcases the creativity, talents, and artistic expressions of the NORSU community.',
        ],
        'norsu-media' => [
            'name' => 'NORSU Media',
            'handle' => '@NORSU Media',
            'description' => 'NORSU Media shares university news, events, and updates to keep the NORSU community informed and connected.',
        ],
    ];

    #[Route('/TN', name: 'app_channel_tn', defaults: ['slug' => 'tn'], methods: ['GET'])]
    #[Route('/Hadurawan', name: 'app_channel_hadurawan', defaults: ['slug' => 'hadurawan'], methods: ['GET'])]
    #[Route('/NorsuMedia', name: 'app_channel_norsu_media', defaults: ['slug' => 'norsu-media'], methods: ['GET'])]
    public function show(string $slug): Response
    {
        $channel = self::CHANNELS[$slug] ?? throw $this->createNotFoundException('Announcement channel not found.');

        return $this->render('announcements/channel.html.twig', ['channel' => $channel]);
    }
}
