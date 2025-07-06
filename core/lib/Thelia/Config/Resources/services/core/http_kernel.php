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

use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ServiceValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\SessionValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\VariadicValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\TheliaHttpKernel;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->alias('base_http_kernel', TheliaHttpKernel::class)
        ->public();

    $services->alias('http_kernel', TheliaHttpKernel::class)
        ->public();

    // Argument resolution
    $services->set('argument_metadata_factory', ArgumentMetadataFactory::class);

    $services->set('argument_resolver', ArgumentResolver::class)
        ->args([
            service('argument_metadata_factory'),
            [], // argument value resolvers collection
        ]);

    $services->alias(ArgumentResolverInterface::class, 'argument_resolver');

    // Argument value resolvers
    $services->set('argument_resolver.request_attribute', RequestAttributeValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 100]);

    $services->set('argument_resolver.request', RequestValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 50]);

    $services->set('argument_resolver.session', SessionValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 50]);

    $services->set('argument_resolver.service', ServiceValueResolver::class)
        ->args([null])
        ->tag('controller.argument_value_resolver', ['priority' => -50]);

    $services->set('argument_resolver.default', DefaultValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => -100]);

    $services->set('argument_resolver.variadic', VariadicValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => -150]);

    // Request and response services
    $services->alias('request', Request::class);
};
