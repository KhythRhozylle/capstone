<?php

namespace App\EventListener;

use App\Service\BrowserSession;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Twig\Environment;

class BrowserSessionListener
{
    public function __construct(
        private readonly Security $security,
        private readonly BrowserSession $sessions,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    #[AsEventListener(event: LoginSuccessEvent::class, priority: -128)]
    public function onLogin(LoginSuccessEvent $event): void
    {
        $key = $event->getRequest()->request->all()[BrowserSession::TOKEN_ATTRIBUTE] ?? null;
        if (!is_string($key) || 1 !== preg_match('/\A[a-f0-9]{64}\z/', $key)) {
            $this->security->logout(false);
            $event->getRequest()->getSession()->getFlashBag()->add('warning', 'Enable JavaScript and browser session storage to sign in.');
            $event->setResponse(new RedirectResponse($this->urls->generate('app_login')));

            return;
        }

        $this->sessions->clearContinuations($event->getRequest());
        $event->getAuthenticatedToken()->setAttribute(BrowserSession::TOKEN_ATTRIBUTE, $key);
    }

    #[AsEventListener(event: RequestEvent::class, priority: 0)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if ('main' !== $this->security->getFirewallConfig($request)?->getName() || 'app_logout' === $request->attributes->get('_route')) {
            return;
        }
        $token = $this->security->getToken();
        if (null === $token) {
            return;
        }
        if (!$token->hasAttribute(BrowserSession::TOKEN_ATTRIBUTE)) {
            // Form-login tokens must never continue without a tab proof.
            // Other authentication methods retain their own session policy.
            if ($token instanceof UsernamePasswordToken) {
                $this->security->logout(false);
                $event->setResponse(new RedirectResponse($this->urls->generate('app_login')));
            }

            return;
        }
        $expected = $token->getAttribute(BrowserSession::TOKEN_ATTRIBUTE);
        $provided = $request->headers->get(BrowserSession::HEADER) ?? ($request->request->all()[BrowserSession::TOKEN_ATTRIBUTE] ?? null);
        if (is_string($provided) && hash_equals($expected, $provided)) {
            return;
        }
        if ($this->sessions->consumeContinuation($request)) {
            return;
        }
        // Images embedded in the authenticated chat cannot attach custom headers.
        if ($request->isMethod('GET') && 'chat_photo' === $request->attributes->get('_route') && 'image' === $request->headers->get('Sec-Fetch-Dest')) {
            return;
        }
        if (in_array($request->getMethod(), ['GET', 'HEAD'], true) && !$request->isXmlHttpRequest() && !$request->headers->has(BrowserSession::HEADER)) {
            $request->attributes->set('_browser_session_check', true);
            $event->setResponse(new Response($this->twig->render('security/session_check.html.twig', [
                'target' => $this->sessions->target($request),
            ])));

            return;
        }

        $this->security->logout(false);
        $event->setResponse(new JsonResponse(['error' => 'Your browser tab session has ended. Sign in again.'], Response::HTTP_UNAUTHORIZED, ['X-Browser-Session-Expired' => '1']));
    }

    #[AsEventListener(event: ResponseEvent::class, priority: -1024)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if ('main' !== $this->security->getFirewallConfig($request)?->getName()) {
            return;
        }
        if ($this->security->getToken()?->hasAttribute(BrowserSession::TOKEN_ATTRIBUTE) || 'app_logout' === $request->attributes->get('_route') || $event->getResponse()->headers->has('X-Browser-Session-Expired')) {
            $event->getResponse()->headers->set('Cache-Control', 'private, no-store');
            $event->getResponse()->headers->set('Referrer-Policy', 'no-referrer');
        }
    }
}
