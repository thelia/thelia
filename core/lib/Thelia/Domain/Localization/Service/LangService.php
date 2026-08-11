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

        $domainUrl = $lang->getUrl();

        if (empty($domainUrl)) {
            $this->logger->warning(
                'The domain URL for language {title} (id {id}) is not defined.',
                ['title' => $lang->getTitle(), 'id' => $lang->getId()],
            );

            return Lang::getDefaultLanguage();
        }

        // If the language domain differs from the current one, redirect to it, keeping the
        // current page when a translation of it exists for the target language.
        if (rtrim($domainUrl, '/') !== $request->getSchemeAndHttpHost()) {
            return new RedirectResponse(
                $this->getTranslatedDomainUrl($request, $domainUrl, $lang),
                Response::HTTP_MOVED_PERMANENTLY,
            );
        }

        return $lang;
    }

    /**
     * Resolve the current rewritten URL to its translation in the target language, so that
     * switching domain keeps the visitor on the same page. Falls back to the bare domain URL
     * when rewriting is disabled, when the current page has no rewritten URL, or when it has
     * no translation in the target language.
     */
    private function getTranslatedDomainUrl(Request $request, string $domainUrl, Lang $lang): string
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
