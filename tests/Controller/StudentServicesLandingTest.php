<?php

namespace App\Tests\Controller;

use App\Entity\SasDirectorate;
use App\Entity\User;
use App\Repository\AnnouncementRepository;
use App\Repository\CampusRepository;
use App\Repository\SasDirectorateRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DomCrawler\Crawler;

class StudentServicesLandingTest extends WebTestCase
{
    public function testServiceCardsLinkToTheirOwnPages(): void
    {
        $crawler = $this->requestHome([
            new SasDirectorate('SDS', 'Student Development Services'),
            new SasDirectorate('SWS', 'Student Welfare Services'),
            new SasDirectorate('ISPS', 'Institutional Student Programs & Services'),
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('#services .service-card'));
        self::assertSame(['/sds', '/sws', '/isps'], $crawler->filter('#services a.service-more')->each(
            static fn (Crawler $link): string => $link->attr('href'),
        ));
        self::assertSame(
            ['Student Development Services', 'Student Welfare Services', 'Institutional Student Programs & Services'],
            $crawler->filter('#services .service-card h3')->each(static fn (Crawler $heading): string => $heading->text()),
        );
        self::assertSame(
            static::getContainer()->get(Packages::class)->getUrl('images/login-building.jpg'),
            $crawler->filter('#services .services-banner > img')->attr('src'),
        );
        foreach ($crawler->filter('#services a.service-more') as $link) {
            self::assertStringContainsString('More', trim($link->textContent));
        }
    }

    public function testServiceLinksRemainVisibleWhenTheDirectorateDirectoryIsEmpty(): void
    {
        $crawler = $this->requestHome();

        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('#services .service-card'));
        self::assertSame(['/sds', '/sws', '/isps'], $crawler->filter('#services a.service-more')->each(
            static fn (Crawler $link): string => $link->attr('href'),
        ));
        self::assertSame(
            static::getContainer()->get(Packages::class)->getUrl('images/login-building.jpg'),
            $crawler->filter('#services .services-banner > img')->attr('src'),
        );
        self::assertSelectorNotExists('#services .landing-empty');
    }

    public function testTeamShowsOnlyThreeSeniorMembersWithTheirExistingPortraits(): void
    {
        $crawler = $this->requestHome(staff: [
            self::staff('Mark', 'Baldado', 'ROLE_STAFF'),
            self::staff('Dianne', 'Gutual-Futalan', 'ROLE_VPSAS_SECRETARIAT'),
            self::staff('Dulce', 'De Guzman', 'ROLE_GUIDANCE'),
            self::staff('Annie Rose', 'Taguba', 'ROLE_DIRECTOR'),
            self::staff('Maria', 'Reyes', 'ROLE_STAFF'),
            self::staff('Cesar', 'Estrope', 'ROLE_VPSAS'),
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('#team .team-member'));
        self::assertSame(['Cesar Estrope', 'Annie Rose Taguba', 'Dianne Gutual-Futalan'], $crawler->filter('#team .team-member h3')->each(
            static fn (Crawler $heading): string => $heading->text(),
        ));
        self::assertSame(['Vice President, Student Affairs & Services', 'Director', 'Secretary'], $crawler->filter('#team .team-title')->each(
            static fn (Crawler $title): string => $title->text(),
        ));
        $portraits = $crawler->filter('#team .team-portrait[aria-hidden="true"] .team-photo-image[alt=""]');
        self::assertCount(3, $portraits);
        self::assertSame(
            array_fill(0, 3, static::getContainer()->get(Packages::class)->getUrl('images/sas-org-chart.jpg')),
            $portraits->each(static fn (Crawler $photo): string => $photo->attr('src')),
        );
        self::assertSelectorNotExists('#team .team-initials');
        self::assertStringNotContainsString('Mark Baldado', $crawler->filter('#team')->text());
        self::assertStringNotContainsString('Dulce De Guzman', $crawler->filter('#team')->text());
        self::assertStringNotContainsString('Maria Reyes', $crawler->filter('#team')->text());
    }

    public function testUnfamiliarTeamMemberUsesInitialsInsteadOfAnotherPersonsPortrait(): void
    {
        $crawler = $this->requestHome(staff: [
            self::staff('Dianne', 'Gutual-Futalan', 'ROLE_VPSAS_SECRETARIAT'),
            self::staff('Lina', 'Santos', 'ROLE_DIRECTOR'),
            self::staff('Cesar', 'Estrope', 'ROLE_VPSAS'),
            self::staff('Mark', 'Baldado', 'ROLE_STAFF'),
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('#team .team-member'));
        $director = $crawler->filter('#team .team-member')->eq(1);
        self::assertSame('Lina Santos', $director->filter('h3')->text());
        self::assertSame('LS', $director->filter('.team-initials')->text());
        self::assertCount(0, $director->filter('.team-photo-image'));
        self::assertCount(2, $crawler->filter('#team .team-photo-image'));
    }

    public function testMembersWithTheSameRankHaveAStableDisplayOrder(): void
    {
        $crawler = $this->requestHome(staff: [
            self::staff('Zara', 'Lopez', 'ROLE_DIRECTOR'),
            self::staff('Dianne', 'Gutual-Futalan', 'ROLE_VPSAS_SECRETARIAT'),
            self::staff('Cesar', 'Estrope', 'ROLE_VPSAS'),
            self::staff('Adam', 'Cruz', 'ROLE_DIRECTOR'),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(['Cesar Estrope', 'Adam Cruz', 'Zara Lopez'], $crawler->filter('#team .team-member h3')->each(
            static fn (Crawler $heading): string => $heading->text(),
        ));
    }

    private static function staff(string $firstName, string $lastName, string $role): User
    {
        return (new User())
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setEmail(strtolower(str_replace(' ', '.', $firstName)).'@example.test')
            ->setRoles([$role]);
    }

    /**
     * @param list<SasDirectorate> $directorateList
     * @param list<User> $staff
     */
    private function requestHome(array $directorateList = [], array $staff = []): Crawler
    {
        $client = static::createClient();

        $campuses = $this->createStub(CampusRepository::class);
        $campuses->method('findBy')->willReturn([]);
        $directorates = $this->createStub(SasDirectorateRepository::class);
        $directorates->method('findBy')->willReturn($directorateList);
        $announcements = $this->createStub(AnnouncementRepository::class);
        $announcements->method('latestUniversityWide')->willReturn([]);
        $users = $this->createStub(UserRepository::class);
        $users->method('findStaff')->willReturn($staff);

        $container = static::getContainer();
        $container->set(CampusRepository::class, $campuses);
        $container->set(SasDirectorateRepository::class, $directorates);
        $container->set(AnnouncementRepository::class, $announcements);
        $container->set(UserRepository::class, $users);

        return $client->request('GET', '/');
    }
}
