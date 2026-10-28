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

namespace Thelia\Tests\Integration\Domain\Ai;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Domain\Ai\Guardrail\AiGuardrailInterface;
use Thelia\Domain\Ai\Guardrail\AiGuardrailRunner;
use Thelia\Test\IntegrationTestCase;

/**
 * A service of a module that implements AiGuardrailInterface reaches the runner: the kernel tags it.
 */
final class AiGuardrailDiscoveryTest extends IntegrationTestCase
{
    public function testTheKernelTagsEveryImplementationOfTheGuardrailInterface(): void
    {
        $container = new ContainerBuilder();

        // The very registration the kernel makes for every container it builds.
        $register = new \ReflectionMethod(self::$kernel, 'loadAutoConfigureInterfaces');
        $register->invoke(self::$kernel, $container);

        $autoconfigured = $container->getAutoconfiguredInstanceof();

        self::assertArrayHasKey(AiGuardrailInterface::class, $autoconfigured);
        self::assertArrayHasKey(AiGuardrailRunner::TAG, $autoconfigured[AiGuardrailInterface::class]->getTags());
    }

    public function testTheRunnerIsWiredInTheContainer(): void
    {
        self::assertInstanceOf(AiGuardrailRunner::class, $this->getService(AiGuardrailRunner::class));
    }
}
