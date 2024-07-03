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

namespace TwigEngine\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Tools\URL;

readonly class URLService
{
    public function __construct(
        private RequestStack $requestStack,
        private ContainerInterface $container
    ) {
    }

    public function generateUrlFunction(string $routeId, array $params): string
    {
        if (!$this->getRequest() instanceof Request) {
            // Symfony route (profiler)
            return '';
        }
        $defaultRouter = null;
        // select default router
        if ($this->getRequest()->fromAdmin()) {
            $defaultRouter = 'admin';
        } elseif ($this->getRequest()->fromFront()) {
            $defaultRouter = 'front';
        }
        $path = $params['path'] ?? null;
        $current = $params['router'] ?? false;
        $routerId = $params['router'] ?? $defaultRouter;
        $baseUrl = $params['base_url'] ?? null;
        $file = $params['file'] ?? null;

        if ($current) {
            $path = $this->getRequest()->getPathInfo();
            unset($params['current']); // Delete the current param, so it isn't included in the url

            // build the query variables
            $params = array_merge(
                $this->getRequest()->query->all(),
                $params
            );
        }

        if ($routerId !== null) {
            $finalRouterId = 'router.'.$routerId;

            // test if the router exists
            if (!$this->container->has($finalRouterId)) {
                throw new \InvalidArgumentException(
                    'The router "'.$finalRouterId.'" not found.'
                );
            }
            // get url by router and id
            /** @var Router $router */
            $router = $this->container->get($finalRouterId);

            $url = $router->generate(
                $routeId,
                $this->getArgsFromParam($params, ['route_id', 'router', 'base_url']),
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        } else {
            if ($file !== null) {
                $path = $file;
                $mode = URL::PATH_TO_FILE;
            } elseif ($path !== null) {
                $mode = URL::WITH_INDEX_PAGE;
            } else {
                throw new \InvalidArgumentException(
                    "Please specify either 'path', 'file' or router and route_id on parameters in 'url' function."
                );
            }

            $excludeParams = $this->resolvePath($params, $path);

            $url = URL::getInstance()->absoluteUrl(
                $path,
                $this->getArgsFromParam($params, array_merge(['noamp', 'path', 'file', 'target', 'base_url'], $excludeParams)),
                $mode,
                $baseUrl
            );

            $request = $this->getRequest();
            $requestedLangCodeOrLocale = $params['lang'] ?? null;
            $view = $request->attributes->get('_view', null);
            $viewId = $view === null ? null : $request->query->get($view.'_id', null);

            if (null !== $requestedLangCodeOrLocale) {
                if (\strlen($requestedLangCodeOrLocale) > 2) {
                    $lang = LangQuery::create()->findOneByLocale($requestedLangCodeOrLocale);
                } else {
                    $lang = LangQuery::create()->findOneByCode($requestedLangCodeOrLocale);
                }
                if (null === $lang) {
                    throw new \InvalidArgumentException(
                        "The lang code or locale '$requestedLangCodeOrLocale' does not exist."
                    );
                }

                if (!Request::$isAdminEnv && ConfigQuery::isMultiDomainActivated()) {
                    $urlRewrite = RewritingUrlQuery::create()
                        ->filterByView($view)
                        ->filterByViewId($viewId)
                        ->filterByViewLocale($lang->getLocale())
                        ->findOneByRedirected(null)
                    ;

                    $path = '';
                    if (null !== $urlRewrite) {
                        $path = '/'.$urlRewrite->getUrl();
                    }
                    $url = rtrim($lang->getUrl(), '/').$request->getBaseUrl().$path;
                }
            }
        }

        return $url;
    }

    protected function resolvePath(array &$params, &$path): array
    {
        $placeholder = [];

        foreach ($params as $key => $value) {
            if (str_contains($path, "%$key")) {
                $placeholder["%$key"] = $this->theliaEscape($value);
                unset($params[$key]);
            }
        }

        $path = strtr($path, $placeholder);
        $keys = array_keys($placeholder);
        array_walk($keys, static function (&$item, $key): void {
            $item = str_replace('%', '', $item);
        });

        return $keys;
    }

    private function getArgsFromParam(array $params, array $exclude = []): array
    {
        $pairs = [];

        foreach ($params as $name => $value) {
            if (\in_array($name, $exclude, true)) {
                continue;
            }

            $pairs[$name] = $value;
        }

        return $pairs;
    }

    protected function getRequest(): \Symfony\Component\HttpFoundation\Request
    {
        return $this->requestStack->getCurrentRequest();
    }

    protected function getSession(): ?SessionInterface
    {
        return $this->getRequest()?->getSession();
    }

    private function theliaEscape($content)
    {
        if (\is_scalar($content)) {
            return htmlspecialchars($content, \ENT_QUOTES, 'UTF-8');
        }

        return $content;
    }
}
