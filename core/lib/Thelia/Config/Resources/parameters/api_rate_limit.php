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
        ->set('env(THELIA_API_RATE_LIMIT_LOGIN_ATTEMPTS)', '5')
        ->set('env(THELIA_API_RATE_LIMIT_LOGIN_ATTEMPTS_PER_CLIENT)', '25');
};
