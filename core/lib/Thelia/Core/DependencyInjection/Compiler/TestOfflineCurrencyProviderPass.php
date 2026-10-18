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

namespace Thelia\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Stops the ECB exchange-rate provider from calling the live feed in the test environment.
 *
 * ECBProvider downloads the ECB feed from its constructor, so every test that
 * builds the container (through the currency action) used to depend on a
 * third-party server, and failed at random when it answered with something
 * that is not XML. The provider is built here without the web service and
 * made public: a test that needs rates loads a fixed feed with loadFromXml().
 *
 * Nothing changes for dev and prod.
 */
class TestOfflineCurrencyProviderPass implements CompilerPassInterface
{
    public const PROVIDER_ID = 'currency.converter.ecbProvider';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::PROVIDER_ID)) {
            return;
        }

        $container->getDefinition(self::PROVIDER_ID)
            ->setArguments([false])
            ->setPublic(true);
    }
}
