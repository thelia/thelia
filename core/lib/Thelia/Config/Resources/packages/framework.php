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
            // The hosting decides where the application cache is stored, with
            // THELIA_CACHE_DSN. Empty, it stays on the local file system.
            'app' => 'thelia.cache.adapter',
            // Namespace every pool key with the install path and the
            // environment, so a shared cache server can serve several shops,
            // and dev and prod of one shop, without any of them reading the
            // keys of another. THELIA_CACHE_PREFIX_SEED overrides it when the
            // same shop is deployed under different paths. Symfony inlines the
            // seed when the container is compiled, so a change takes effect on
            // the next cache clear, and no cast may be applied here.
            'prefix_seed' => '%env(THELIA_CACHE_PREFIX_SEED)%',
            'pools' => [
                // Dedicated pool for the in-process data access layer
                // (DataAccessService::resources). Disabled by default; enable
                // per project with THELIA_DATA_ACCESS_CACHE=1. Clearing this
                // pool never affects the rest of the application cache.
                'thelia.cache.data_access' => [
                    'adapter' => 'cache.app',
                ],
                // API refresh tokens. Kept apart because it is the only pool
                // whose eviction is visible to a user: losing an item here
                // signs an API client out. Nothing empties it on a deployment
                // or on a cache clear; each item carries its own lifetime.
                'thelia.cache.security' => [
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
            // A guest order tracking link is the only thing standing between a stranger
            // and someone else's order, and it names the order by a small number, so a
            // caller who cannot guess the signature can still try. Both windows are
            // needed: the per-order one keeps a single order from being hammered, the
            // per-client one stops a single caller from walking the order numbers.
            // Room for someone reloading their own tracking page, none for a sweep.
            'guest_order_access_per_order' => [
                'policy' => 'sliding_window',
                'limit' => 10,
                'interval' => '15 minutes',
            ],
            'guest_order_access_per_client' => [
                'policy' => 'sliding_window',
                'limit' => 30,
                'interval' => '15 minutes',
            ],
            // Opening a guest account takes no credential: it writes a customer row and
            // hands back a token for whatever address it is given. Both windows are
            // needed: the per-address one keeps one mailbox from being used over and
            // over to probe whether it already has an account, the per-client one stops
            // a single caller from walking a list of addresses or filling the table.
            // Room for a visitor correcting a typo and starting over, none for a sweep.
            'guest_registration_per_address' => [
                'policy' => 'sliding_window',
                'limit' => 5,
                'interval' => '1 hour',
            ],
            'guest_registration_per_client' => [
                'policy' => 'sliding_window',
                'limit' => 20,
                'interval' => '1 hour',
            ],
        ],
    ], prepend: true);
};
