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

namespace Thelia\Tests\Unit\Log;

use PHPUnit\Framework\TestCase;
use Thelia\Log\AbstractTlogDestination;
use Thelia\Log\TlogDestinationConfig;

/**
 * A destination applies its configuration as soon as it is built, keeps the
 * configuration parameters it declares and applies them again when one changes.
 */
final class AbstractTlogDestinationTest extends TestCase
{
    public function testTheConfigurationIsAppliedWhenTheDestinationIsBuilt(): void
    {
        self::assertSame(1, $this->destination()->configurations);
    }

    public function testTheDeclaredConfigurationParametersAreKeptWhenTheDestinationIsBuilt(): void
    {
        $destination = $this->destination();

        self::assertSame('debug', $destination->getConfig('level'));
        self::assertFalse($destination->getConfig('unknown'));
    }

    public function testChangingAParameterAppliesTheConfigurationAgain(): void
    {
        $destination = $this->destination();

        self::assertTrue($destination->setConfig('level', 'error'));
        self::assertSame('error', $destination->getConfig('level'));
        self::assertSame(2, $destination->configurations);
    }

    public function testAParameterCanChangeWithoutApplyingTheConfiguration(): void
    {
        $destination = $this->destination();

        self::assertTrue($destination->setConfig('level', 'error', false));
        self::assertSame('error', $destination->getConfig('level'));
        self::assertSame(1, $destination->configurations);
    }

    public function testAnUndeclaredParameterIsNotSet(): void
    {
        self::assertFalse($this->destination()->setConfig('unknown', 'error'));
    }

    public function testADestinationThatDeclaresNoParameterExposesNone(): void
    {
        $destination = new class extends AbstractTlogDestination {
            public function write(&$res): void
            {
            }

            public function getTitle(): string
            {
                return 'Test';
            }

            public function getDescription(): string
            {
                return 'Test';
            }
        };

        self::assertSame([], $destination->getConfigs());
    }

    private function destination(): object
    {
        return new class extends AbstractTlogDestination {
            public int $configurations = 0;

            public function write(&$res): void
            {
            }

            public function getTitle(): string
            {
                return 'Test';
            }

            public function getDescription(): string
            {
                return 'Test';
            }

            // The real destinations build their parameters here, a fresh object on each call.
            public function getConfigs(): array
            {
                return [
                    // TlogDestinationConfig reads its value from the database when built.
                    new class('level', 'debug') extends TlogDestinationConfig {
                        public function __construct(string $name, string $value)
                        {
                            $this->name = $name;
                            $this->value = $value;
                        }
                    },
                ];
            }

            protected function configure(): void
            {
                parent::configure();
                ++$this->configurations;
            }
        };
    }
}
