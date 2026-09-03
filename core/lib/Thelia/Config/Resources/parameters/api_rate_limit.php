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
    $container->parameters()
        // How fast the API may be called, and how many login attempts it accepts.
        // These are operating settings: what a shop can absorb depends on its
        // hosting, not on its catalogue, so they are read from the environment
        // and not from the database or the back-office. Every one of them has a
        // working default here, so a shop that sets nothing is still protected.
        //
        // Failed login attempts, per minute. The first is counted per caller and
        // per identifier, which is what stops someone trying passwords on one
        // account; the second is counted per caller only, which is what stops
        // someone trying one password across many accounts. The wider one has to
        // stay well above the narrow one, or a shared office address would lock
        // itself out on the first colleague who mistypes.
        ->set('env(THELIA_API_RATE_LIMIT_LOGIN_ATTEMPTS)', '10')
        ->set('env(THELIA_API_RATE_LIMIT_LOGIN_ATTEMPTS_PER_CLIENT)', '50')
        // Token refreshes, per minute and per caller. A client refreshes once
        // per token lifetime, so twenty a minute leaves room for a client that
        // holds several sessions at once and still stops a caller working
        // through refresh tokens.
        ->set('env(THELIA_API_RATE_LIMIT_TOKEN_REFRESH)', '20')
        // Everything else, per minute.
        ->set('env(THELIA_API_RATE_LIMIT_ANONYMOUS)', '200')
        ->set('env(THELIA_API_RATE_LIMIT_FRONT_AUTHENTICATED)', '800')
        ->set('env(THELIA_API_RATE_LIMIT_ADMIN)', '2000')
        // Addresses and CIDR ranges exempt from the limits that are not about
        // logging in, comma separated. Meant for a synchronisation that
        // legitimately calls faster than a browser does.
        ->set('env(THELIA_API_RATE_LIMIT_ALLOWLIST)', '');
};
