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

namespace Thelia\Domain\Localization;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Domain\Localization\Service\LocaleDirection;
use Thelia\Model\Admin;
use Thelia\Model\Lang;

final readonly class LocalizationFacade
{
    public function __construct(
        private LangService $langService,
        // Both defaulted so the single-argument constructor this facade used to have keeps
        // working: LocaleDirection holds a hard-coded list and no state, so building one is
        // free and always correct, and the logger is only used to explain a fallback.
        private LocaleDirection $localeDirection = new LocaleDirection(),
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Return the current language for the main request (or null if no request/session).
     */
    public function getCurrentLang(): ?Lang
    {
        return $this->langService->getLang();
    }

    /**
     * Return the current locale (falls back to default language locale).
     */
    public function getCurrentLocale(): ?string
    {
        return $this->langService->getLocale();
    }

    /**
     * The writing direction of the current language, ready for an HTML "dir" attribute.
     *
     * Read from the locale rather than from the language: getCurrentLang() answers null
     * outside a session - a console command, a worker, a message consumer - while the
     * locale falls back to the default language of the shop. A template asks for a
     * direction to write an attribute with it, so this never throws and never answers
     * an empty string: a shop with no default language at all still reads left to right.
     *
     * @return LocaleDirection::LEFT_TO_RIGHT|LocaleDirection::RIGHT_TO_LEFT
     */
    public function getCurrentLangDirection(): string
    {
        try {
            return $this->localeDirection->forLocale($this->langService->getLocale());
        } catch (\Throwable $failure) {
            // The expected failure is a shop with no default language, but resolving the
            // locale also reaches the database and the session, so this catches whatever
            // comes. Losing a page over a "dir" attribute would be a poor trade, and a
            // silent one would be worse: the direction degrades, the cause is written
            // down, and the real symptom stays findable in the log.
            $this->logger?->warning(
                'Could not resolve the current locale, falling back to a left-to-right writing direction.',
                ['exception' => $failure],
            );

            return LocaleDirection::LEFT_TO_RIGHT;
        }
    }

    /**
     * Set the current language on session and update the request locale.
     */
    public function setCurrentLang(Lang $lang): void
    {
        $this->langService->setLang($lang);
    }

    /**
     * Resolve front language from request parameters or domain.
     * May return a RedirectResponse if multi-domain requires a host change.
     *
     * - If a "lang" or "locale" parameter is present, it will be used.
     * - Else returns the session language if any.
     * - Else tries to resolve by domain when multi-domain is enabled.
     * - Else returns the default language.
     */
    public function resolveFrontLanguage(Request $request): Lang|Response
    {
        return $this->langService->resolveFrontLanguageFromRequest($request);
    }

    /**
     * Resolve admin language from request (query param "lang") or session/default.
     */
    public function resolveAdminLanguage(Request $request): Lang
    {
        return $this->langService->resolveAdminLanguageFromRequest($request);
    }

    /**
     * Resolve admin language from an Admin user preferred locale or default.
     */
    public function resolveAdminLanguageFromUser(Admin $admin): Lang
    {
        return $this->langService->resolveAdminLanguageFromAdmin($admin);
    }

    /**
     * Synchronize session language with current domain when multi-domain is enabled.
     */
    public function syncFrontLanguageWithDomain(Request $request): void
    {
        $this->langService->syncMultiDomainLanguage($request);
    }

    /**
     * Handle language for the current request context (front or admin).
     * On admin: sets admin language in session.
     * On front: may return a redirect if multi-domain requires it.
     *
     * Returns:
     * - Lang when admin and resolved successfully,
     * - Lang|Response when front (redirect may be required),
     * - null when nothing to change.
     */
    public function handleLanguage(Session $session, Request $request): Lang|Response|null
    {
        return $this->langService->handleLang($session, $request);
    }
}
