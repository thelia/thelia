<?php

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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Exception\UrlRewritingException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Rewriting\RewritingResolver;
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

    public function match(string $pathinfo): array
    {
        return $this->matchRequest(Request::create($pathinfo));
    }

    /**
     * @throws UrlRewritingException
     */
    public function matchRequest(Request $request): array
    {
        if (!ConfigQuery::isRewritingEnable()) {
            throw new ResourceNotFoundException();
        }

        $urlTool = URL::getInstance();

        $pathInfo = $request instanceof TheliaRequest ? $request->getRealPathInfo() : $request->getPathInfo();

        try {
            $rewrittenUrlData = $urlTool->resolve($pathInfo);
        } catch (UrlRewritingException $e) {
            throw match ($e->getCode()) {
                UrlRewritingException::URL_NOT_FOUND => new ResourceNotFoundException(),
                default => $e,
            };
        }

        // Check if there is a "lang" parameter in the request
        $requestedLocale = $request->get('lang');
        if ($requestedLocale !== null) {
            // Find the requested language by locale if it's active
            $requestedLang = LangQuery::create()
                ->filterByActive(true)
                ->findOneByLocale($requestedLocale);

            if ($requestedLang !== null && $requestedLang->getLocale() !== $rewrittenUrlData->locale) {
                // Retrieve the localized URL and perform a redirection
                $localizedUrl = $urlTool->retrieve(
                    $rewrittenUrlData->view,
                    $rewrittenUrlData->viewId,
                    $requestedLang->getLocale()
                )->toString();

                $this->redirect($urlTool->absoluteUrl($localizedUrl), 301);
            }
        }

        // If the rewritten URL locale is disabled, redirect to the URL in the default language
        if (null === LangQuery::create()
                ->filterByActive(true)
                ->filterByLocale($rewrittenUrlData->locale)
                ->findOne()) {
            $lang = Lang::getDefaultLanguage();

            $localizedUrl = $urlTool->retrieve(
                $rewrittenUrlData->view,
                $rewrittenUrlData->viewId,
                $lang->getLocale()
            )->toString();

            $this->redirect($urlTool->absoluteUrl($localizedUrl), 301);
        }

        /* is the URL redirected ? */
        if (null !== $rewrittenUrlData->redirectedToUrl) {
            $redirect = RewritingUrlQuery::create()
                ->filterByView($rewrittenUrlData->view)
                ->filterByViewId($rewrittenUrlData->viewId)
                ->filterByViewLocale($rewrittenUrlData->locale)
                ->filterByRedirected(null, Criteria::ISNULL)
                ->findOne()
            ;

            $this->redirect($urlTool->absoluteUrl($redirect?->getUrl()), 301);
        }

        /* define GET arguments in request */

        if (null !== $rewrittenUrlData->view) {
            $request->attributes->set('_view', $rewrittenUrlData->view);
            if (null !== $rewrittenUrlData->viewId) {
                $request->query->set($rewrittenUrlData->view.'_id', $rewrittenUrlData->viewId);
            }
        }

        if (null !== $rewrittenUrlData->locale) {
            $this->manageLocale($rewrittenUrlData, $request);
        }

        foreach ($rewrittenUrlData->otherParameters as $parameter => $value) {
            $request->query->set($parameter, $value);
        }

        return [
            '_controller' => 'Thelia\\Controller\\Front\\DefaultController::noAction',
            '_route' => 'rewrite',
            '_rewritten' => true,
        ];
    }

    protected function manageLocale(RewritingResolver $rewrittenUrlData, TheliaRequest $request): void
    {
        $lang = LangQuery::create()
            ->filterByActive(true)
            ->findOneByLocale($rewrittenUrlData->locale);

        $langSession = $request->getSession()->getLang();

        if ($lang->getLocale() !== $langSession->getLocale()) {
            if (ConfigQuery::isMultiDomainActivated()) {
                $this->redirect(
                    sprintf('%s/%s', $lang->getUrl(), $rewrittenUrlData->rewrittenUrl),
                    301
                );
            } else {
                $request->getSession()->setLang($lang);
            }
        }
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
