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

namespace Thelia\Core\Routing;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Controller\Front\DefaultController;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Core\Routing\Rewriting\Exception\UrlRewritingException;
use Thelia\Core\Routing\Rewriting\RewritingResolver;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Tools\URL;

/**
 * Class RewritingRouter.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class RewritingRouter implements RouterInterface, RequestMatcherInterface
{
    protected RequestContext $context;
    protected array $options;

    public function __construct(
        protected LangService $langService,
    ) {
    }

    public function match(string $pathinfo): array
    {
        $request = Request::create($pathinfo);

        return $this->matchRequest($request);
    }

    /**
     * @throws UrlRewritingException
     * @throws PropelException
     */
    public function matchRequest(Request $request): array
    {
        if (!$this->isSupportedRequest($request)) {
            throw new ResourceNotFoundException();
        }
        \assert($request instanceof TheliaRequest);

        $pathInfo = $request->getRealPathInfo();
        $resolver = $this->resolveRewritingData($pathInfo);

        $this->maybeRedirectForRequestedLocale($request, $resolver);
        $this->ensureActiveLocaleOrRedirect($resolver);
        $this->maybeRedirectForManualRedirect($resolver);

        $this->applyRewritingAttributes($request, $resolver);

        return $this->defaultRouteParams();
    }

    protected function isSupportedRequest(Request $request): bool
    {
        return $request instanceof TheliaRequest && ConfigQuery::isRewritingEnable();
    }

    /**
     * @throws PropelException
     * @throws UrlRewritingException
     */
    protected function resolveRewritingData(string $pathInfo): RewritingResolver
    {
        $urlTool = URL::getInstance();

        try {
            return $urlTool->resolve($pathInfo);
        } catch (UrlRewritingException $e) {
            throw match ($e->getCode()) {
                UrlRewritingException::URL_NOT_FOUND => new ResourceNotFoundException(),
                default => $e,
            };
        }
    }

    protected function maybeRedirectForRequestedLocale(Request $request, RewritingResolver $resolver): void
    {
        $requestedLocale = $request->get('lang');

        if (null === $requestedLocale) {
            return;
        }

        $requestedLang = LangQuery::create()
            ->filterByActive(true)
            ->findOneByLocale($requestedLocale);

        if (null !== $requestedLang && $requestedLang->getLocale() !== $resolver->locale) {
            $localizedUrl = URL::getInstance()
                ->retrieve($resolver->view, $resolver->viewId, $requestedLang->getLocale())
                ->toString();

            $this->redirect(URL::getInstance()->absoluteUrl($localizedUrl), 301);
        }
    }

    protected function ensureActiveLocaleOrRedirect(RewritingResolver $resolver): void
    {
        $active = LangQuery::create()
            ->filterByActive(true)
            ->filterByLocale($resolver->locale)
            ->findOne();

        if (null !== $active) {
            return;
        }

        $default = Lang::getDefaultLanguage();

        $localizedUrl = URL::getInstance()
            ->retrieve($resolver->view, $resolver->viewId, $default->getLocale())
            ->toString();

        $this->redirect(URL::getInstance()->absoluteUrl($localizedUrl), 301);
    }

    protected function maybeRedirectForManualRedirect(RewritingResolver $resolver): void
    {
        if (null === $resolver->redirectedToUrl) {
            return;
        }

        $redirect = RewritingUrlQuery::create()
            ->filterByView($resolver->view)
            ->filterByViewId($resolver->viewId)
            ->filterByViewLocale($resolver->locale)
            ->filterByRedirected(null, Criteria::ISNULL)
            ->findOne();

        $this->redirect(URL::getInstance()->absoluteUrl($redirect?->getUrl()), 301);
    }

    private function applyRewritingAttributes(Request $request, RewritingResolver $resolver): void
    {
        if (null !== $resolver->view) {
            $request->attributes->set('_view', $resolver->view);

            if (null !== $resolver->viewId) {
                $request->query->set($resolver->view.'_id', $resolver->viewId);
            }
        }

        if (null !== $resolver->locale && $request instanceof TheliaRequest) {
            $this->manageLocale($resolver, $request);
        }

        foreach ($resolver->otherParameters as $parameter => $value) {
            $request->query->set($parameter, $value);
        }
    }

    protected function manageLocale(RewritingResolver $resolver, TheliaRequest $request): void
    {
        $lang = LangQuery::create()
            ->filterByActive(true)
            ->findOneByLocale($resolver->locale);

        $currentLang = $request->getSession()->getLang();

        if ($lang->getLocale() !== $currentLang?->getLocale()) {
            if (ConfigQuery::isMultiDomainActivated()) {
                $this->redirect(
                    \sprintf('%s/%s', $lang->getUrl(), $resolver->rewrittenUrl),
                    301,
                );
            } else {
                $this->langService->setLang($lang);
            }
        }
    }

    private function defaultRouteParams(): array
    {
        return [
            '_controller' => DefaultController::class.'::noAction',
            '_route' => 'rewrite',
            '_rewritten' => true,
        ];
    }

    protected function redirect($url, $status = 302): void
    {
        throw new RedirectException($url, $status);
    }

    public function setContext(RequestContext $context): void
    {
        $this->context = $context;
    }

    public function getContext(): RequestContext
    {
        return $this->context;
    }

    public function setOption($key, $value): void
    {
        // NOTHING TO DO FOR NOW
    }

    public function getRouteCollection(): RouteCollection
    {
        return new RouteCollection();
    }

    public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH): string
    {
        throw new RouteNotFoundException();
    }
}
