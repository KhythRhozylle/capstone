<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ServiceDirectorTest extends WebTestCase
{
    #[DataProvider('directors')]
    public function testServicePageShowsTheDirectorAndHiddenUnitHeads(string $path, string $director, int $heads): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#service-director-name', $director);
        self::assertSelectorExists('button.vpsas-face[aria-expanded="false"][aria-controls="service-heads"]');
        self::assertSelectorExists('#service-heads[hidden]');
        self::assertCount($heads, $crawler->filter('#service-heads .vpsas-member'));
        self::assertCount($heads, $crawler->filter('#service-heads button[data-action="profile#open"]'));
        self::assertSelectorExists('.profile-modal[hidden] .profile-close');
        // Every profile has exactly six picture tiles (no text) and no social statistics.
        self::assertCount($heads * 6, $crawler->filter('template img.profile-tile'));
        self::assertStringNotContainsStringIgnoringCase('followers', $crawler->html());
    }

    /** @return iterable<string, array{string, string, int}> */
    public static function directors(): iterable
    {
        yield 'SDS' => ['/sds', 'Julio E. Ventolero', 4];
        yield 'SWS' => ['/sws', 'Annie Rose Taguba', 2];
        yield 'ISPS' => ['/isps', 'Carlou G. Bernaldez', 6];
    }
}
