<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StudentServiceControllerTest extends WebTestCase
{
    #[DataProvider('services')]
    public function testServicePagesArePublicAndExplainTheirServices(string $code, string $name): void
    {
        $client = static::createClient();
        $client->request('GET', '/'.strtolower($code));

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains($name);
        self::assertSelectorTextContains('h1#service-title', $name);
        self::assertSelectorExists('body.service-detail-page');
        self::assertSelectorTextContains('.service-detail-copy', $name.' ('.$code.')');
        self::assertSelectorNotExists('.service-offering');
        self::assertSelectorExists('a.service-back[href="/#services"]');
        self::assertSelectorExists('.service-help-actions a[href="/login"]');
        self::assertSelectorExists('.service-help-actions a[href="/contact"]');
        foreach (['sds', 'sws', 'isps'] as $path) {
            self::assertSelectorExists('.service-related-links a[href="/'.$path.'"]');
        }
        self::assertSelectorExists('.service-related-links a[href="/'.strtolower($code).'"][aria-current="page"]');
    }

    #[DataProvider('serviceCodes')]
    public function testServicePagesOmitCampusAndUnitGalleries(string $code): void
    {
        $client = static::createClient();
        $client->request('GET', '/'.strtolower($code));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#service-gallery-heading');
        self::assertSelectorNotExists('.service-detail-gallery');
        self::assertSelectorNotExists('.service-gallery-note');
        self::assertSelectorNotExists('#service-offerings-heading');
        self::assertSelectorNotExists('.service-unit-galleries');
        self::assertSelectorNotExists('.unit-gallery');
        self::assertSelectorExists('.service-people');
        self::assertSelectorExists('.service-help');
    }

    public static function services(): iterable
    {
        yield 'student development' => ['SDS', 'Student Development Services'];
        yield 'student welfare' => ['SWS', 'Student Welfare Services'];
        yield 'institutional programs' => ['ISPS', 'Institutional Student Programs & Services'];
    }

    public static function serviceCodes(): iterable
    {
        yield 'student development' => ['SDS'];
        yield 'student welfare' => ['SWS'];
        yield 'institutional programs' => ['ISPS'];
    }
}
