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

namespace Thelia\Tests\Unit\Api\Bridge\Propel\Filter;

use ApiPlatform\Metadata\Operation;
use PHPUnit\Framework\TestCase;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Thelia\Api\Bridge\Propel\Filter\AbstractFilter;

/**
 * The logger handed to a filter is the one its subclasses write to, and a filter given none
 * writes nowhere.
 */
final class AbstractFilterTest extends TestCase
{
    public function testTheLoggerOfAFilterIsTheOneItWasGiven(): void
    {
        $logger = $this->createStub(LoggerInterface::class);

        self::assertSame($logger, $this->filter($logger)->loggerInUse());
    }

    public function testAFilterWithoutALoggerWritesNowhere(): void
    {
        self::assertInstanceOf(NullLogger::class, $this->filter(null)->loggerInUse());
    }

    private function filter(?LoggerInterface $logger): object
    {
        return new class($logger) extends AbstractFilter {
            public function loggerInUse(): LoggerInterface
            {
                return $this->getLogger();
            }

            protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
            {
            }

            public function getDescription(string $resourceClass): array
            {
                return [];
            }
        };
    }
}
