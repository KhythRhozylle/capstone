<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DomCrawler\Crawler;

class StudentServiceControllerTest extends WebTestCase
{
    #[DataProvider('services')]
    public function testServicePagesArePublicAndExplainTheirServices(string $code, string $name, array $unitNames, array $unitCodes): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/'.strtolower($code));

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains($name);
        self::assertSelectorTextContains('h1#service-title', $name);
        self::assertSelectorExists('body.service-detail-page');
        self::assertSelectorTextContains('.service-detail-copy', $name.' ('.$code.')');
        self::assertSame($unitNames, $crawler->filter('.unit-gallery h3')->each(
            static fn (Crawler $heading): string => $heading->text(),
        ));
        self::assertSelectorNotExists('.service-offering');
        self::assertSelectorNotExists('.unit-gallery p');
        self::assertSelectorExists('a.service-back[href="/#services"]');
        self::assertSelectorExists('.service-help-actions a[href="/login"]');
        self::assertSelectorExists('.service-help-actions a[href="/contact"]');
        foreach (['sds', 'sws', 'isps'] as $path) {
            self::assertSelectorExists('.service-related-links a[href="/'.$path.'"]');
        }
        self::assertSelectorExists('.service-related-links a[href="/'.strtolower($code).'"][aria-current="page"]');
    }

    #[DataProvider('serviceCodes')]
    public function testServiceImagesUseTheAccessibleCampusCarousel(string $code): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/'.strtolower($code));
        $galleryId = strtolower($code).'-carousel';

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.service-detail-gallery[data-controller="carousel"][role="region"][aria-roledescription="carousel"][aria-labelledby="service-gallery-heading"]');
        self::assertSelectorExists('#'.$galleryId.'-track[tabindex="0"][aria-describedby="'.$galleryId.'-help"][aria-live="off"]');
        self::assertSame(
            'keydown.left->carousel#prev:prevent keydown.right->carousel#next:prevent',
            $crawler->filter('#'.$galleryId.'-track')->attr('data-action'),
        );
        self::assertCount(2, $crawler->filter('#'.$galleryId.'-track article[role="group"][aria-roledescription="slide"]'));
        $assets = static::getContainer()->get(Packages::class);
        foreach (['images/login-building.jpg', 'images/newlogin.jpeg'] as $image) {
            self::assertSelectorExists('#'.$galleryId.'-track img[src="'.$assets->getUrl($image).'"][alt]:not([alt=""])');
        }
        self::assertSelectorExists('.service-detail-gallery .carousel-prev[hidden][aria-controls="'.$galleryId.'-track"][data-action="carousel#prev"]');
        self::assertSelectorExists('.service-detail-gallery .carousel-next[hidden][aria-controls="'.$galleryId.'-track"][data-action="carousel#next"]');
        self::assertSelectorTextContains('#'.$galleryId.'-help', 'left and right arrow keys');
    }

    #[DataProvider('services')]
    public function testEveryUnitHasItsOwnAccessibleImageGallery(string $code, string $name, array $unitNames, array $unitCodes): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/'.strtolower($code));

        self::assertResponseIsSuccessful();
        $galleries = $crawler->filter('section.unit-gallery');
        self::assertCount(count($unitNames), $galleries);
        self::assertSame($unitCodes, $galleries->each(
            static fn (Crawler $gallery): string => $gallery->attr('data-unit-code'),
        ));

        $galleryIds = [];
        $galleries->each(static function (Crawler $gallery) use ($code, &$galleryIds): void {
            $galleryId = $gallery->attr('id');
            self::assertMatchesRegularExpression('/^'.strtolower($code).'-[a-z0-9]+(?:-[a-z0-9]+)*-gallery$/', $galleryId);
            $galleryIds[] = $galleryId;
            self::assertCount(1, $gallery->filter('h3'));

            $frames = $gallery->filter('.unit-gallery-grid > figure.unit-gallery-item');
            self::assertGreaterThanOrEqual(2, $frames->count());
            $frames->each(static function (Crawler $frame): void {
                self::assertSame('0', $frame->attr('tabindex'));
                $image = $frame->filter('img');
                self::assertCount(1, $image);
                self::assertNotEmpty(trim($image->attr('alt')));
                self::assertNotEmpty($image->attr('src'));
            });
        });

        self::assertCount(count($unitNames), array_unique($galleryIds));
    }

    #[DataProvider('carouselPages')]
    public function testCarouselPagesOmitTheStatusAndPlaybackRow(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.carousel-controls');
        self::assertSelectorNotExists('.carousel-dots');
        self::assertSelectorNotExists('.carousel-status');
        self::assertSelectorNotExists('.carousel-playback');
    }

    public static function services(): iterable
    {
        yield 'student development' => ['SDS', 'Student Development Services', [
            'University OSAS',
            'Student Government',
            'Board of Discipline & Campus Student Organization',
            'Sports Development Program',
        ], ['OSAS', 'GOV', 'DISC', 'SPORT']];
        yield 'student welfare' => ['SWS', 'Student Welfare Services', [
            'CARE — Counselling, Assessment, Resources & Enhancement',
            'Student Enterprise Development',
        ], ['CARE', 'ENT']];
        yield 'institutional programs' => ['ISPS', 'Institutional Student Programs & Services', [
            'Scholarship and Financial Assistance',
            'Multi-Faith Services',
            'Student Publication',
            'Foreign / International Student Services',
            'Food, Student Housing & Residential',
            'Services for Students with Special Needs',
        ], ['SCHOL', 'FAITH', 'PUB', 'INTL', 'HOUS', 'SSN']];
    }

    public static function serviceCodes(): iterable
    {
        yield 'student development' => ['SDS'];
        yield 'student welfare' => ['SWS'];
        yield 'institutional programs' => ['ISPS'];
    }

    public static function carouselPages(): iterable
    {
        yield 'home' => ['/'];
        yield 'student development' => ['/sds'];
        yield 'student welfare' => ['/sws'];
        yield 'institutional programs' => ['/isps'];
    }
}
