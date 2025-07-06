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

use Thelia\Log\Tlog;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        ->set('import.base_url', '/admin/import')
        ->set('export.base_url', '/admin/export')
        ->set('thelia.token_id', 'thelia.token_provider')
        ->set('thelia.validator.translation_domain', 'validators')
        ->set('thelia.logger.class', Tlog::class)
        ->set('thelia.cache.namespace', 'thelia_cache');
};
