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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Cmf\Component\Routing\ChainRouter;
use Symfony\Cmf\Component\Routing\DynamicRouter;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Router;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        ->set('router.request_context.class', RequestContext::class)
        ->set('router.dynamicRouter.class', DynamicRouter::class)
        ->set('router.chainRouter.class', ChainRouter::class)
        ->set('router.class', Router::class)
        ->set('router.xmlFileName', 'routing.php');
};
