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

use Thelia\Controller\Api\RefreshTokenController;
use Thelia\Core\Security\RefreshToken\AuthenticationSuccessSubscriber;
use Thelia\Core\Security\RefreshToken\RefreshTokenService;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Security\UserProvider\AdminUserProvider;
use Thelia\Core\Security\UserProvider\CustomerUserProvider;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->alias('thelia.securityContext', SecurityContext::class)
        ->public();

    $container->parameters()
        // How long a refresh token stays valid, in seconds. Read when the token
        // is issued, not when the container is compiled, so a hosting that
        // lowers it in .env.local does not have to clear the cache for it.
        ->set('env(JWT_REFRESH_TOKEN_TTL)', '2592000')
        ->set('thelia.security.jwt_refresh_token_ttl', '%env(int:JWT_REFRESH_TOKEN_TTL)%');

    $services->set(AuthenticationSuccessSubscriber::class)
        ->args([service(RefreshTokenService::class)])
        ->tag('kernel.event_subscriber');

    $services->set(RefreshTokenController::class)
        ->args([
            service(RefreshTokenService::class),
            service('lexik_jwt_authentication.jwt_manager'),
            service(AdminUserProvider::class),
            service(CustomerUserProvider::class),
        ])
        ->public()
        ->tag('controller.service_arguments');
};
