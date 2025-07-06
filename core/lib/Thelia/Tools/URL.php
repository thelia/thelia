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

namespace Thelia\Tools;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraints\UrlValidator;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\ConfigQuery;
use Thelia\Service\Rewriting\RewritingResolver;
use Thelia\Service\Rewriting\RewritingRetriever;

class URL
{
    protected RewritingResolver $resolver;

    protected RewritingRetriever $retriever;

    /** @var RequestContext */
    protected $requestContext;

    public const PATH_TO_FILE = true;

    public const WITH_INDEX_PAGE = false;

    protected static self $instance;

    /** @var string a cache for the base URL scheme */
    private ?string $baseUrlScheme = null;

    public function __construct(?RouterInterface $router = null)
    {
        // Allow singleton style calls once instantiated.
        // For this to work, the URL service has to be instantiated very early. This is done manually
        // in TheliaHttpKernel, by calling $this->container->get('thelia.url.manager');
        self::$instance = $this;

        if ($router instanceof RouterInterface) {
            $this->requestContext = $router->getContext();
        }

        $this->retriever = new RewritingRetriever();
        $this->resolver = new RewritingResolver();
    }

    /**
     * @since Version 2.2
     */
    public function setRequestContext(RequestContext $requestContext): void
    {
        $this->requestContext = $requestContext;
    }

    /**
     * Return this class instance, only once instanciated.
     *
     * @throws \RuntimeException if the class has not been instanciated
     *
     * @return URL the instance
     */
    public static function getInstance(): self
    {
        if (!self::$instance instanceof self) {
            throw new \RuntimeException('URL instance is not initialized.');
        }

        return self::$instance;
    }

    /**
     * Return the base URL, either the base_url defined in Config, or the URL
     * of the current language, if 'one_domain_foreach_lang' is enabled.
     *
     * @param bool $scheme_only if true, only the scheme will be returned. If false, the complete base URL, including path, is returned.
     *
     * @return string the base URL, with a trailing '/'
     */
    public function getBaseUrl($scheme_only = false): string
    {
        if (null === $this->baseUrlScheme) {
            $scheme = 'http';
            $port = 80;

            if ($host = $this->requestContext->getHost()) {
                $scheme = $this->requestContext->getScheme();

                $port = '';

                if ('http' === $scheme && 80 != $this->requestContext->getHttpPort()) {
                    $port = ':'.$this->requestContext->getHttpPort();
                } elseif ('https' === $scheme && 443 != $this->requestContext->getHttpsPort()) {
                    $port = ':'.$this->requestContext->getHttpsPort();
                }
            }

            $this->baseUrlScheme = \sprintf('%s://%s%s', $scheme, $host, $port);
        }

        return $scheme_only ? $this->baseUrlScheme : $this->baseUrlScheme.$this->requestContext->getBaseUrl();
    }

    /**
     * @return string the index page, which is in fact the base URL
     */
    public function getIndexPage(): string
    {
        // The index page is the base URL :)
        return $this->getBaseUrl();
    }

    /**
     * Returns the Absolute URL for a given path relative to web root. By default,
     * the script name (index_dev.php) is added to the URL in dev_environment, use
     * $path_only = true to get a path without the index script.
     *
     * @param string $path             the relative path
     * @param array  $parameters       An array of parameters
     * @param bool   $path_only        if true (PATH_TO_FILE), getIndexPage() will  not be added
     * @param string $alternateBaseUrl if not null, this URL is unsed instead of the base URL. Useful for creating CDN URLs
     *
     * @return string The generated URL
     */
    public function absoluteUrl($path, ?array $parameters = null, $path_only = self::WITH_INDEX_PAGE, $alternateBaseUrl = null): string
    {
        // Already absolute ?
        if (!str_starts_with($path, 'http')) {
            if (empty($alternateBaseUrl)) {
                // Prevent duplication of the subdirectory name when Thelia is installed in a subdirectory.
                // This happens when $path was calculated with Router::generate(), which returns an absolute URL,
                // starting at web server root. For example, if Thelia is installed in /thelia2, we got something like /thelia2/my/path
                // As base URL also contains /thelia2 (e.g. http://some.server.com/thelia2), we end up with
                // http://some.server.com/thelia2/thelia2/my/path, instead of http://some.server.com/thelia2/my/path
                // We have to compensate for this.
                $rcbu = $this->requestContext->getBaseUrl();

                $hasSubdirectory = !empty($rcbu) && str_starts_with($path, $rcbu);

                $base_url = $this->getBaseUrl($hasSubdirectory);
            } else {
                $base_url = $alternateBaseUrl;
            }

            // If only a path is requested, be sure to remove the script name (index.php or index_dev.php), if any.
            if ($path_only == self::PATH_TO_FILE && str_ends_with($base_url, 'php')) {
                $base_url = \dirname($base_url);
            }

            // Normalize the given path
            $base = rtrim($base_url, '/').'/'.ltrim($path, '/');
        } else {
            $base = $path;
        }

        $base = str_replace('&amp;', '&', $base);

        $queryString = '';
        $anchor = '';

        if (null !== $parameters) {
            foreach ($parameters as $name => $value) {
                // Remove this parameter from base URL to prevent duplicate parameters
                $base = preg_replace('`([?&])'.preg_quote($name, '`').'=(?:[^&]*)(?:&|$)`', '$1', (string) $base);

                $queryString .= \sprintf('%s=%s&', urlencode($name), urlencode((string) $value));
            }
        }

        if ('' !== $queryString = rtrim($queryString, '&')) {
            // url could contain anchor
            $pos = strrpos((string) $base, '#');
            if ($pos !== false) {
                $anchor = substr((string) $base, $pos);
                $base = substr((string) $base, 0, $pos);
            }

            $base = rtrim((string) $base, '?&');

            $sepChar = str_contains($base, '?') ? '&' : '?';

            $queryString = $sepChar.$queryString;
        }

        return $base.$queryString.$anchor;
    }

    /**
     * Returns the Absolute URL to a administration view.
     *
     * @param string $viewName   the view name (e.g. login for login.html)
     * @param mixed  $parameters An array of parameters
     *
     * @return string The generated URL
     */
    public function adminViewUrl($viewName, array $parameters = []): string
    {
        $path = \sprintf('%s/admin/%s', $this->getIndexPage(), $viewName);

        return $this->absoluteUrl($path, $parameters);
    }

    /**
     * Returns the Absolute URL to a view.
     *
     * @param string $viewName   the view name (e.g. login for login.html)
     * @param mixed  $parameters An array of parameters
     *
     * @return string The generated URL
     */
    public function viewUrl($viewName, array $parameters = []): string
    {
        $path = \sprintf('?view=%s', $viewName);

        return $this->absoluteUrl($path, $parameters);
    }

    /**
     * Retrieve a rewritten URL from a view, a view id and a locale.
     *
     * @return RewritingRetriever You can access $url and $rewrittenUrl properties
     */
    public function retrieve(string $view, $viewId, $viewLocale): RewritingRetriever
    {
        if (ConfigQuery::isRewritingEnable()) {
            $this->retriever->loadViewUrl($view, $viewLocale, $viewId);
        } else {
            $allParametersWithoutView = [];
            $allParametersWithoutView['lang'] = $viewLocale;
            if (null !== $viewId) {
                $allParametersWithoutView[$view.'_id'] = $viewId;
            }

            $this->retriever->rewrittenUrl = null;
            $this->retriever->url = self::getInstance()->viewUrl($view, $allParametersWithoutView);
        }

        return $this->retriever;
    }

    /**
     * Retrieve a rewritten URL from the current GET parameters.
     *
     * @return RewritingRetriever You can access $url and $rewrittenUrl properties or use toString method
     */
    public function retrieveCurrent(Request $request): RewritingRetriever
    {
        $allOtherParameters = $request->query->all();

        if (ConfigQuery::isRewritingEnable()) {
            $view = $request->attributes->get('_view', null);

            $viewLocale = $this->getViewLocale($request);

            $viewId = $view === null ? null : $request->query->get($view.'_id', null);

            if ($view !== null) {
                unset($allOtherParameters['view']);
                if ($viewId !== null) {
                    unset($allOtherParameters[$view.'_id']);
                }
            }

            if ($viewLocale !== null) {
                unset($allOtherParameters['lang']);
                unset($allOtherParameters['locale']);
            }

            $this->retriever->loadSpecificUrl($view, $viewLocale, $viewId, $allOtherParameters);
        } else {
            $allParametersWithoutView = $request->query->all();
            $view = $request->attributes->get('_view');
            if (isset($allOtherParameters['view'])) {
                unset($allOtherParameters['view']);
            }

            $this->retriever->rewrittenUrl = null;
            $this->retriever->url = self::getInstance()->viewUrl($view, $allParametersWithoutView);
        }

        return $this->retriever;
    }

    /**
     * Retrieve a rewritten URL from the current GET parameters or use toString method.
     */
    public function resolve($url): RewritingResolver
    {
        $this->resolver->load($url);

        return $this->resolver;
    }

    protected function sanitize($string, $force_lowercase = true, $alphabetic_only = false): string|array|null
    {
        static $strip = ['~', '`', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '_', '=', '+', '[', '{', ']',
            '}', '\\', '|', ';', ':', '"', "'", '&#8216;', '&#8217;', '&#8220;', '&#8221;', '&#8211;', '&#8212;',
            'â€”', 'â€“', ',', '<', '.', '>', '/', '?', ];

        $clean = trim(str_replace($strip, '', strip_tags((string) $string)));

        $clean = preg_replace('/\s+/', '-', $clean);

        $clean = ($alphabetic_only) ? preg_replace('/[^a-zA-Z0-9]/', '', (string) $clean) : $clean;

        return ($force_lowercase) ?
             (\function_exists('mb_strtolower')) ?
                 mb_strtolower((string) $clean, 'UTF-8') :
             strtolower((string) $clean) :
             $clean;
    }

    public static function checkUrl($url, array $protocols = ['http', 'https']): bool
    {
        $pattern = \sprintf(UrlValidator::PATTERN, implode('|', $protocols));

        return (bool) preg_match($pattern, (string) $url);
    }

    /**
     * Get the locale code from the lang attribute in URL.
     *
     * @return string|null
     */
    private function getViewLocale(Request $request)
    {
        $viewLocale = $request->query->get('lang', null);
        if (null === $viewLocale) {
            // fallback for old parameter
            $viewLocale = $request->query->get('locale', null);
        }

        if (null === $viewLocale && $request->getSession() instanceof SessionInterface) {
            // fallback to session or default language
            $viewLocale = $request->getSession()->getLang()->getLocale();
        }

        return $viewLocale;
    }
}
