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

namespace Thelia\Messenger;

use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RequestContext;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;

/**
 * A request on the address of the shop, for work done outside any page: a job of a worker, a
 * command, a scheduled task. A worker serves no page, but what it renders does as a page does:
 * the loops of a template, the layout of a mail and its links read the request and its session.
 *
 * Built from DEFAULT_URI (the request context of the router), folder included, with an empty
 * session: the work sees no visitor, no cart and the default language.
 */
final readonly class ShopRequestFactory
{
    public function __construct(
        private RequestContext $requestContext,
    ) {
    }

    public function create(): Request
    {
        // Created without a script, a request has no base path: the folder of a shop
        // installed below the root of its site would be lost from every link.
        $basePath = rtrim($this->requestContext->getBaseUrl(), '/');
        $request = Request::create($this->shopAddress(), 'GET', [], [], [], [
            'SCRIPT_NAME' => $basePath.'/index.php',
            'SCRIPT_FILENAME' => 'index.php',
        ]);
        $session = new Session(new MockArraySessionStorage());
        // The cart a session makes before it is saved is held in a static: the work starts
        // without the one a previous job made.
        $session->setSessionCart(null);
        $request->setSession($session);

        return $request;
    }

    private function shopAddress(): string
    {
        $context = $this->requestContext;
        $port = 'https' === $context->getScheme() ? $context->getHttpsPort() : $context->getHttpPort();
        $defaultPort = 'https' === $context->getScheme() ? 443 : 80;

        return \sprintf(
            '%s://%s%s%s/',
            $context->getScheme(),
            $context->getHost(),
            $port === $defaultPort ? '' : ':'.$port,
            rtrim($context->getBaseUrl(), '/'),
        );
    }
}
