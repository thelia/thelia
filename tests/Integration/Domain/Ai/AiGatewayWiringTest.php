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

use Thelia\Domain\Ai\AiGatewayInterface;
use Thelia\Domain\Ai\Gateway\AiGateway;
use Thelia\Test\IntegrationTestCase;

/**
 * What a module gets when it injects the interface: the gateway of the core, wired with everything it needs.
 */
final class AiGatewayWiringTest extends IntegrationTestCase
{
    public function testTheInterfaceIsTheGatewayOfTheCore(): void
    {
        self::assertInstanceOf(AiGateway::class, $this->getService(AiGatewayInterface::class));
    }
}
