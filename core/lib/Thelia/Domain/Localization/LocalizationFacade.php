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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\Admin;
use Thelia\Model\Lang;

final readonly class LocalizationFacade
{
    public function __construct(private LangService $langService)
    {
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
