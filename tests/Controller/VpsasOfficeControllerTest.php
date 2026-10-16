<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class VpsasOfficeControllerTest extends WebTestCase
{
    public function testOfficePageIsPublicAndShowsTheTeam(): void
    {
        $client = static::createClient();
        $client->request('GET', '/office-of-the-vice-president');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.vpsas-name', 'Cesar P. Estrope');
        self::assertSelectorExists('button.vpsas-face[aria-expanded="false"][aria-controls="vpsas-team"]');
        self::assertSelectorExists('#vpsas-team[hidden] .vpsas-member');
        self::assertSelectorTextContains('#vpsas-team', 'Secretary');
        self::assertSelectorTextContains('#vpsas-team', 'Office Staff');
    }
}
