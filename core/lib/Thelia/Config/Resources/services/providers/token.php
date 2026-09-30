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

use Thelia\Tools\TokenProvider;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();
    $parameters = $configurator->parameters();

    // Token provider
    $services->set(TokenProvider::class)
        ->args([
            service('request_stack'),
            service('thelia.translator'),
            param('thelia.token_id'),
            param('thelia.token.accept_query_string'),
        ]);

    $services->alias('thelia.token_provider', TokenProvider::class);

    $parameters->set('thelia.token_id', 'thelia.token_provider');
    // A token read from the URL leaks through access logs, history and Referer headers. It is
    // still accepted, with a deprecation, until the back offices send it in the body or a header.
    $parameters->set('thelia.token.accept_query_string', true);
    $parameters->set('thelia.validator.translation_domain', 'validators');
};
