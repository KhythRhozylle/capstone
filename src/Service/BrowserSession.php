<?php

namespace App\Service;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class BrowserSession
{
    public const TOKEN_ATTRIBUTE = '_browser_session';
    public const HEADER = 'X-Browser-Session';
    public const CONTINUATION = '_browser_continue';
    private const GRANTS = '_browser_session_grants';

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function clearContinuations(Request $request): void
    {
        $request->getSession()->remove(self::GRANTS);
    }

    public function continuationUrl(Request $request, string $target): string
    {
        if (str_contains($target, self::CONTINUATION)) {
            throw new BadRequestHttpException('Invalid continuation target.');
        }

        $now = $this->clock->now()->getTimestamp();
        $grants = array_filter($request->getSession()->get(self::GRANTS, []), static fn (array $grant): bool => $grant['expires'] >= $now);
        $grants = array_slice($grants, -19, null, true);
        $ticket = bin2hex(random_bytes(32));
        $grants[$ticket] = ['target' => $target, 'expires' => $now + 30];
        $request->getSession()->set(self::GRANTS, $grants);

        return $target.(str_contains($target, '?') ? '&' : '?').self::CONTINUATION.'='.$ticket;
    }

    public function consumeContinuation(Request $request): bool
    {
        $ticket = $request->query->get(self::CONTINUATION);
        if (!is_string($ticket) || 1 !== preg_match('/\A[a-f0-9]{64}\z/', $ticket) || !in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return false;
        }

        $grants = $request->getSession()->get(self::GRANTS, []);
        $grant = $grants[$ticket] ?? null;
        unset($grants[$ticket]);
        $request->getSession()->set(self::GRANTS, $grants);
        if (null === $grant || $grant['expires'] < $this->clock->now()->getTimestamp()) {
            return false;
        }

        $expected = $grant['target'].(str_contains($grant['target'], '?') ? '&' : '?').self::CONTINUATION.'='.$ticket;
        if ($expected !== $request->getRequestUri()) {
            return false;
        }

        $request->query->remove(self::CONTINUATION);

        return true;
    }

    public function target(Request $request): string
    {
        $query = $request->query->all();
        unset($query[self::CONTINUATION]);

        return $request->getPathInfo().($query ? '?'.http_build_query($query) : '');
    }
}
