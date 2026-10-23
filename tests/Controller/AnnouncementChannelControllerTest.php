<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AnnouncementChannelControllerTest extends WebTestCase
{
    #[DataProvider('channels')]
    public function testChannelPageIsPublic(string $path, string $name): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1#channel-title', $name);
        self::assertSelectorExists('a.service-back[href="/#announcements"]');
    }

    /** @return iterable<string, array{string, string}> */
    public static function channels(): iterable
    {
        yield 'TN' => ['/TN', 'The NORSUnian'];
        yield 'Hadurawan' => ['/Hadurawan', 'HADURAWAN'];
        yield 'NORSU Media' => ['/NorsuMedia', 'NORSU Media'];
    }
}
