<?php

namespace App\Tests\Controller;

use App\Entity\Campus;
use App\Repository\AnnouncementRepository;
use App\Repository\CampusRepository;
use App\Repository\SasDirectorateRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class CampusCarouselTest extends WebTestCase
{
    public function testCampusPhotosRemainAvailableBeforeJavaScriptConnects(): void
    {
        $crawler = $this->requestHome([
            new Campus('MAIN', 'Dumaguete Main Campus'),
            new Campus('BAIS1', 'Bais Campus I'),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#campuses .carousel[role="region"][aria-roledescription="carousel"][aria-labelledby="campuses-heading"]');
        self::assertSelectorExists('#campus-carousel-track[tabindex="0"][aria-describedby="campus-carousel-help"][aria-live="off"]');
        self::assertSame(
            'keydown.left->carousel#prev:prevent keydown.right->carousel#next:prevent',
            $crawler->filter('#campus-carousel-track')->attr('data-action'),
        );
        self::assertCount(2, $crawler->filter('#campus-carousel-track article[role="group"][aria-roledescription="slide"]'));
        self::assertSelectorExists('#campus-carousel-track article[aria-label="1 of 2: Dumaguete Main Campus"] img[src][alt=""]');
        self::assertSelectorExists('#campus-carousel-track article[aria-label="2 of 2: Bais Campus I"] img[src][alt=""]');
        self::assertSame(['Dumaguete Main Campus', 'Bais Campus I'], $crawler->filter('.carousel-caption h3')->each(
            static fn (Crawler $caption): string => $caption->text(),
        ));
        self::assertSelectorExists('.carousel-prev[hidden][aria-controls="campus-carousel-track"][data-carousel-target="previous"][data-action="carousel#prev"]');
        self::assertSelectorExists('.carousel-next[hidden][aria-controls="campus-carousel-track"][data-carousel-target="next"][data-action="carousel#next"]');
        self::assertSelectorNotExists('#campuses .carousel-controls');
        self::assertSelectorNotExists('#campuses .carousel-dots');
        self::assertSelectorNotExists('#campuses .carousel-status');
        self::assertSelectorNotExists('#campuses .carousel-playback');
        self::assertSelectorNotExists('#campuses .carousel-playback-status');
        self::assertSelectorNotExists('#campuses [data-action*="carousel#pauseOnHover"]');
        self::assertSelectorTextContains('#campus-carousel-help', 'Swipe or scroll');
    }

    public function testEmptyCampusDirectoryHasNoInactiveCarouselControls(): void
    {
        $this->requestHome([]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#campuses .landing-empty', 'Campus list coming soon.');
        self::assertSelectorNotExists('#campuses [data-controller="carousel"]');
        self::assertSelectorNotExists('#campuses .carousel-btn');
    }

    /** @param list<Campus> $campusList */
    private function requestHome(array $campusList): Crawler
    {
        $client = static::createClient();

        $campuses = $this->createMock(CampusRepository::class);
        $campuses->expects(self::once())->method('findBy')->with([], ['id' => 'ASC'])->willReturn($campusList);

        $directorates = $this->createStub(SasDirectorateRepository::class);
        $directorates->method('findBy')->willReturn([]);
        $announcements = $this->createStub(AnnouncementRepository::class);
        $announcements->method('latestUniversityWide')->willReturn([]);
        $users = $this->createStub(UserRepository::class);
        $users->method('findStaff')->willReturn([]);

        $container = static::getContainer();
        $container->set(CampusRepository::class, $campuses);
        $container->set(SasDirectorateRepository::class, $directorates);
        $container->set(AnnouncementRepository::class, $announcements);
        $container->set(UserRepository::class, $users);

        return $client->request('GET', '/');
    }
}
