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

namespace Thelia\Domain\Localization\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Routing\Rewriting\Exception\UrlRewritingException;
use Thelia\Core\Routing\Rewriting\RewritingResolver;
use Thelia\Model\Admin;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\RewritingUrlQuery;

readonly class LangService
{
    public function __construct(
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {
    }

    public function getLang(): ?Lang
    {
        $request = $this->requestStack->getMainRequest();

        if (!$request instanceof Request || !$request->hasSession()) {
            return null;
        }

        return $request->getSession()->getLang();
    }

    public function setLang(Lang $lang): void
    {
        $request = $this->requestStack->getMainRequest();

        if (!$request instanceof Request || !$request->hasSession()) {
            return;
        }

        $request->getSession()->setLang($lang);
        $request->setLocale($lang->getLocale());
    }

    public function handleLang(Session $session, Request $request): Response|Lang|null
    {
        if (true === Request::$isAdminEnv) {
            $lang = $this->resolveAdminLanguageFromRequest($request);
            $session->setAdminLang($lang);

            return $lang;
        }

        $langOrResponse = $this->resolveFrontLanguageFromRequest($request);

        if ($langOrResponse instanceof Response) {
            return $langOrResponse;
        }

        if ($langOrResponse instanceof Lang) {
            $this->setLang($langOrResponse);
        }

        return null;
    }

    public function resolveFrontLanguageFromRequest(Request $request): Lang|Response
    {
        $requestedLang = $this->getLanguageFromRequestParameters($request);

        if (null !== $requestedLang) {
            return $this->handleLanguageWithDomainRedirect($requestedLang, $request);
        }

        $sessionLang = $request->getSession()->getLang(false);

        if ($sessionLang instanceof Lang) {
            return $sessionLang;
        }

        if (ConfigQuery::isMultiDomainActivated()) {
            $domainLang = $this->getLanguageByDomain($request);

            if (null !== $domainLang) {
                return $domainLang;
            }
        }

        return Lang::getDefaultLanguage();
    }

    public function resolveAdminLanguageFromRequest(Request $request): Lang
    {
        $requestedLangCodeOrLocale = $request->query->get('lang');

        if (null !== $requestedLangCodeOrLocale) {
            $lang = LangQuery::create()->findOneByCode($requestedLangCodeOrLocale);

            if ($lang instanceof Lang) {
                return $lang;
            }
        }

        return $request->getSession()->getLang() ?? Lang::getDefaultLanguage();
    }

    public function resolveAdminLanguageFromAdmin(Admin $adminUser): Lang
    {
        $lang = LangQuery::create()->findOneByLocale($adminUser->getLocale());

        return $lang instanceof Lang ? $lang : Lang::getDefaultLanguage();
    }

    public function syncMultiDomainLanguage(Request $request): void
    {
        if (Request::$isAdminEnv || !ConfigQuery::isMultiDomainActivated()) {
            return;
        }

        $session = $request->getSession();

        $currentLang = $session->getLang();
        $domainUrl = $currentLang?->getUrl();

        if (!empty($domainUrl) && rtrim($domainUrl, '/') !== $request->getSchemeAndHttpHost()) {
            $langs = LangQuery::create()
                ->filterByActive(true)
                ->filterByVisible(true)
                ->find();

            foreach ($langs as $lang) {
                $langDomainUrl = $lang->getUrl();

                if (rtrim($langDomainUrl, '/') === $request->getSchemeAndHttpHost()) {
                    $session->setLang($lang);
                    break;
                }
            }
        }
    }

    private function getLanguageFromRequestParameters(Request $request): ?Lang
    {
        $requestedLangCodeOrLocale = $request->query->get('lang') ?? $request->query->get('locale');

        if (null === $requestedLangCodeOrLocale) {
            return null;
        }

        $isLocale = \strlen($requestedLangCodeOrLocale) > 2;
        $query = LangQuery::create()->filterByActive(true);

        return $isLocale
            ? $query->findOneByLocale($requestedLangCodeOrLocale)
            : $query->findOneByCode($requestedLangCodeOrLocale);
    }

    private function handleLanguageWithDomainRedirect(Lang $lang, Request $request): Lang|Response
    {
        if (!ConfigQuery::isMultiDomainActivated()) {
            return $lang;
        }

        // A language switch is a navigation, and a redirect is the only way to answer one.
        // Answering anything else with a redirect is not: a browser replays a 301 as a GET
        // and drops the body, so a checkout posted with a "lang" parameter in its action
        // url would be silently thrown away instead of being taken.
        if (!$request->isMethodSafe()) {
            return $lang;
        }

        $domainUrl = $lang->getUrl();

        if (empty($domainUrl)) {
            $this->logger->warning(
                'The domain URL for language {title} (id {id}) is not defined.',
                ['title' => $lang->getTitle(), 'id' => $lang->getId()],
            );

            return Lang::getDefaultLanguage();
        }

        // Already on the domain of the requested language: nothing to redirect to.
        if (rtrim($domainUrl, '/') === $request->getSchemeAndHttpHost()) {
            return $lang;
        }

        // The current page is kept when a translation of it exists for the target language.
        $targetUrl = $this->withRequestedQueryString(
            $request,
            $this->translatedDomainUrl($request, $domainUrl, $lang),
        );

        // The check above compares two strings, and a domain written with a different case
        // or with its default port spelled out does not match the one being browsed. Such a
        // shop would be answered with a redirect to the very url it asked for, which tells
        // the browser nothing but to ask again.
        if ($this->isCurrentUrl($request, $targetUrl)) {
            return $lang;
        }

        return new RedirectResponse($targetUrl, Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * A redirect to the url being served is never a useful answer: it only tells the browser
     * to ask the same question again. Queries are ignored on purpose, since dropping them
     * still lands on the same page, and so still loops.
     */
    private function isCurrentUrl(Request $request, string $url): bool
    {
        $target = parse_url($url);

        if (false === $target) {
            return false;
        }

        // A host-less target is relative to the host being browsed. getHost() and getScheme()
        // both answer in lower case, which is what a host and a scheme are compared in.
        $sameHost = !isset($target['host'])
            || (
                strtolower($target['host']) === $request->getHost()
                && strtolower($target['scheme'] ?? $request->getScheme()) === $request->getScheme()
                && ($target['port'] ?? $request->getPort()) === $request->getPort()
            );

        return $sameHost
            && trim($target['path'] ?? '', '/') === trim($request->getRealPathInfo(), '/');
    }

    /**
     * Resolve the current rewritten URL to its translation in the target language, so that
     * switching domain keeps the visitor on the same page. Falls back to the bare domain URL
     * when rewriting is disabled, when the current page has no rewritten URL, or when it has
     * no translation in the target language.
     */
    private function translatedDomainUrl(Request $request, string $domainUrl, Lang $lang): string
    {
        if (!ConfigQuery::isRewritingEnable()) {
            return $domainUrl;
        }

        $currentPath = ltrim($request->getRealPathInfo(), '/');

        if ('' === $currentPath) {
            return $domainUrl;
        }

        try {
            $currentUrl = new RewritingResolver($currentPath);
        } catch (UrlRewritingException) {
            return $domainUrl;
        }

        $translatedUrl = RewritingUrlQuery::create()
            ->filterByView($currentUrl->view)
            ->filterByViewId($currentUrl->viewId)
            ->filterByViewLocale($lang->getLocale())
            ->filterByRedirected(null, Criteria::ISNULL)
            ->findOne();

        if (null === $translatedUrl) {
            return $domainUrl;
        }

        return rtrim($domainUrl, '/').'/'.$translatedUrl->getUrl();
    }

    /**
     * The page has to survive the language switch, and so does its state: a paginated
     * listing, a sort order, a campaign tag are all carried by the query string. They are
     * read from the query string of the request rather than from the query bag, which any
     * listener running before this one is free to write into.
     *
     * "lang" and "locale" are dropped: they have been consumed here, and the url they point
     * to already is the one of the requested language. Carrying them over would also turn a
     * domain the shop cannot match - written http:// on a shop served in https, say - into
     * an endless redirect, each hop asking for the same switch again.
     */
    private function withRequestedQueryString(Request $request, string $url): string
    {
        $parameters = [];
        parse_str((string) $request->getQueryString(), $parameters);

        unset($parameters['lang'], $parameters['locale']);

        if ([] === $parameters) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($parameters);
    }

    private function getLanguageByDomain(Request $request): ?Lang
    {
        return LangQuery::create()
            ->filterByUrl($request->getSchemeAndHttpHost(), Criteria::LIKE)
            ->findOne();
    }

    public function getLocale(): ?string
    {
        $locale = $this->getLang()?->getLocale();

        return $locale ?? Lang::getDefaultLanguage()->getLocale();
    }
}
