<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeControllerTest extends WebTestCase
{
    public function testLandingPageRendersAllSections(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        foreach (['welcome', 'about', 'campuses', 'people', 'services', 'announcements', 'team'] as $section) {
            self::assertCount(1, $crawler->filter('section#'.$section), \sprintf('Missing section "%s".', $section));
        }
        self::assertSelectorExists('.landing-nav a[href="/contact"]');
        foreach (['Home' => 'welcome', 'About' => 'about', 'Team' => 'team'] as $label => $section) {
            self::assertSame($label, $crawler->filter('.landing-nav a[href="/#'.$section.'"]')->text());
        }
        self::assertStringNotContainsString('NORSU-SAS Insight', $crawler->filter('.landing-nav')->text());
        self::assertSelectorExists('#welcome .hero-emblem > img.hero-logo[width="240"]');
        self::assertSelectorExists('#people .org-tilt[data-controller="tilt"] .org-glass > img');
        self::assertSelectorNotExists('#contact');
        self::assertSelectorExists('#people img.org-chart-image');
        self::assertSelectorNotExists('.module-grid');
    }

    public function testSignInButtonsOpenTheMorphingLoginModal(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('a[data-action="login-morph#open"][href="/login"]'));
        self::assertSelectorExists('#login-modal[hidden] form[action="/login"] input[name="_csrf_token"]');
    }

    public function testLoginPageStillWorksWithoutJavaScript(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.login-tilt form[action="/login"] input[name="_password"]');
    }
}
