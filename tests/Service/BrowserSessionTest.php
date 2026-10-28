<?php

namespace App\Tests\Service;

use App\Service\BrowserSession;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class BrowserSessionTest extends KernelTestCase
{
    public function testNavigationTicketsExpireAndCannotBeReplayed(): void
    {
        $clock = new MockClock('2026-10-27 12:00:00 UTC');
        $sessions = new BrowserSession($clock);
        $request = Request::create('/session/continue', 'POST');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $url = $sessions->continuationUrl($request, '/staff?view=one');
        $navigation = Request::create($url);
        $navigation->setSession($request->getSession());
        self::assertTrue($sessions->consumeContinuation($navigation));
        self::assertFalse($navigation->query->has(BrowserSession::CONTINUATION));
        $replay = Request::create($url);
        $replay->setSession($request->getSession());
        self::assertFalse($sessions->consumeContinuation($replay));

        $url = $sessions->continuationUrl($request, '/staff');
        $clock->sleep(31);
        $expired = Request::create($url);
        $expired->setSession($request->getSession());
        self::assertFalse($sessions->consumeContinuation($expired));
    }

    public function testOnlyExactGetAndHeadTargetsCanConsumeATicket(): void
    {
        $sessions = new BrowserSession(new MockClock());
        $request = Request::create('/session/continue', 'POST');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $url = $sessions->continuationUrl($request, '/staff?view=one');
        $changed = Request::create(str_replace('view=one', 'view=two', $url));
        $changed->setSession($request->getSession());
        self::assertFalse($sessions->consumeContinuation($changed));

        foreach (['POST', 'OPTIONS', 'TRACE'] as $method) {
            $url = $sessions->continuationUrl($request, '/staff');
            $navigation = Request::create($url, $method);
            $navigation->setSession($request->getSession());
            self::assertFalse($sessions->consumeContinuation($navigation));
        }
        $url = $sessions->continuationUrl($request, '/staff');
        $head = Request::create($url, 'HEAD');
        $head->setSession($request->getSession());
        self::assertTrue($sessions->consumeContinuation($head));
    }
}
