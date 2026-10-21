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
        // How long a signed preview link of a content or a folder keeps working, in days.
        // Long enough to pass a draft around for review, short enough that a link left in
        // a mailbox does not show the shop's drafts for good.
        ->set('thelia.publication.preview_lifetime_days', 7);
};
