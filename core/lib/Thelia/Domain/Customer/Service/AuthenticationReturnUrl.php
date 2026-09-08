<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Domain\Customer\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Thelia\Tools\RedirectUrl;

/**
 * Carries the page a visitor was on until they have signed in.
 *
 * Signing in interrupts what someone was doing — filling a cart, reading a product
 * sheet, going through the checkout — and the account pages are rarely where they
 * meant to go. The interrupted URL travels as the "redirect" parameter of the
 * sign-in links, is remembered for the length of the journey (a failed attempt, the
 * two registration steps, the activation code) and is handed back once the session
 * holds a customer.
 *
 * Two things it deliberately does not do: read the Referer header, which the browser
 * may strip or a third-party page may set, and read the previous URL the core keeps
 * in session, which the sign-in page itself overwrites as soon as it is rendered.
 */
readonly class AuthenticationReturnUrl
{
    /**
     * Query (or form) parameter naming the page to come back to. Anything a visitor
     * puts in it is checked against the current host before use.
     */
    public const PARAMETER = 'redirect';

    private const SESSION_KEY = 'thelia.authentication_return_url';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    /**
     * The value a sign-in link should carry to come back to $request.
     */
    public function of(Request $request): string
    {
        return $request->getRequestUri();
    }

    /**
     * The parameter carried by the current request, when it points inside the shop.
     */
    public function requested(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            return null;
        }

        $url = $request->query->get(self::PARAMETER) ?? $request->request->get(self::PARAMETER);

        if (!\is_string($url) || !RedirectUrl::isSafe($url, $request->getHost())) {
            return null;
        }

        return $url;
    }

    /**
     * Remembers the return URL the current request carries.
     *
     * Called by the pages of the sign-in journey: the parameter reaches the first of
     * them only, and the session carries it through the steps that follow. A request
     * without the parameter leaves what is already remembered untouched, so landing on
     * the registration form from the sign-in page does not lose the destination.
     */
    public function capture(): void
    {
        $url = $this->requested();

        if (null === $url) {
            return;
        }

        $this->session()?->set(self::SESSION_KEY, $url);
    }

    /**
     * Where to send a visitor who just signed in, and forgets it.
     *
     * The parameter of the current request wins over what was remembered: it is the
     * more recent intent, and a form that posts one says where it wants to go.
     */
    public function consume(string $default): string
    {
        $session = $this->session();
        $remembered = $session?->get(self::SESSION_KEY);

        $this->forget();

        $url = $this->requested();

        if (null === $url && \is_string($remembered) && $this->isSafe($remembered)) {
            $url = $remembered;
        }

        return $url ?? $default;
    }

    /**
     * Drops the remembered URL, for a caller that decides the destination itself.
     */
    public function forget(): void
    {
        $this->session()?->remove(self::SESSION_KEY);
    }

    private function isSafe(string $url): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request instanceof Request && RedirectUrl::isSafe($url, $request->getHost());
    }

    private function session(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request || !$request->hasSession()) {
            return null;
        }

        return $request->getSession();
    }
}
