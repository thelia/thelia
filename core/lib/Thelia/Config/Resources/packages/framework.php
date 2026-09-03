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

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'session' => [
            'save_path' => '%kernel.project_dir%/var/sessions/%kernel.environment%',
        ],
        'cache' => [
            'pools' => [
                // Dedicated pool for the in-process data access layer
                // (DataAccessService::resources). Disabled by default; enable
                // per project with THELIA_DATA_ACCESS_CACHE=1. Clearing this
                // pool never affects the rest of the application cache.
                'thelia.cache.data_access' => [
                    'adapter' => 'cache.app',
                ],
            ],
        ],
        'rate_limiter' => [
            // Asking for a new account activation code, or for a new password, sends an
            // email to an address the visitor typed, so an unauthenticated caller can
            // make the shop mail someone else. Both limits are needed: the per-address
            // one protects the owner of a single mailbox, the per-client one stops a
            // single caller from walking a list of addresses. Three an hour leaves room
            // for the usual "it did not arrive, send it again" without a real customer
            // ever noticing the cap.
            'customer_email_request_per_address' => [
                'policy' => 'sliding_window',
                'limit' => 3,
                'interval' => '1 hour',
            ],
            'customer_email_request_per_client' => [
                'policy' => 'sliding_window',
                'limit' => 10,
                'interval' => '1 hour',
            ],
            // Login attempts on the two API login endpoints. The narrow window is
            // per caller and per identifier, the wide one per caller: one stops
            // passwords being tried on a single account, the other stops one
            // password being tried across many. Both figures come from the
            // environment (see parameters/api_rate_limit.php) so an operator can
            // move them without touching the code. A sliding window is used
            // rather than a fixed one so the budget cannot be spent twice around
            // the moment a fixed window would roll over.
            'api_login_per_client_and_identifier' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:THELIA_API_RATE_LIMIT_LOGIN_ATTEMPTS)%',
                'interval' => '1 minute',
            ],
            'api_login_per_client' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:THELIA_API_RATE_LIMIT_LOGIN_ATTEMPTS_PER_CLIENT)%',
                'interval' => '1 minute',
            ],
            // Token refreshes. A client asks for one when its access token is
            // about to expire, so a caller asking again and again is either
            // misbuilt or trying refresh tokens one after the other.
            'api_token_refresh_per_client' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:THELIA_API_RATE_LIMIT_TOKEN_REFRESH)%',
                'interval' => '1 minute',
            ],
            // Everything else under /api. An anonymous caller is counted per
            // address; an authenticated one per account, so a whole office
            // behind a single address is not held to one budget. The
            // administration figure is the highest because one back-office
            // screen fans out into several calls.
            'api_anonymous' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:THELIA_API_RATE_LIMIT_ANONYMOUS)%',
                'interval' => '1 minute',
            ],
            'api_front_authenticated' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:THELIA_API_RATE_LIMIT_FRONT_AUTHENTICATED)%',
                'interval' => '1 minute',
            ],
            'api_admin' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:THELIA_API_RATE_LIMIT_ADMIN)%',
                'interval' => '1 minute',
            ],
        ],
    ], prepend: true);
};
