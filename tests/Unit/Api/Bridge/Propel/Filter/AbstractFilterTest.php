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
use Propel\Runtime\Map\TableMap;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Thelia\Api\Bridge\Propel\Filter\AbstractFilter;
use Thelia\Api\Resource\I18n;
use Thelia\Api\Resource\I18nCollection;
use Thelia\Api\Resource\TranslatableResourceInterface;

/**
 * The logger handed to a filter is the one its subclasses write to, and a filter given none
 * writes nowhere.
 *
 * A filtered property resolves to the SQL path of its column: the table column for a plain
 * property, the join alias for a relation path, and the i18n join for a translatable field
 * the resource does not declare itself.
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

    public function testAFieldATranslatableResourceDoesNotDeclareIsReadFromItsTranslations(): void
    {
        self::assertSame('localized:title', $this->queryPath('title', TranslatedFilteredProduct::class));
    }

    public function testAFieldATranslatableResourceDeclaresIsReadFromItsOwnTable(): void
    {
        self::assertSame('product.created_at', $this->queryPath('createdAt', TranslatedFilteredProduct::class));
    }

    public function testARelationPathOnATranslatableResourceIsReadFromTheJoinedTable(): void
    {
        self::assertSame('translatedfilteredproduct_brand.created_at', $this->queryPath('brand.createdAt', TranslatedFilteredProduct::class));
    }

    public function testAFieldAResourceWithoutTranslationsDoesNotDeclareIsStillReadFromItsOwnTable(): void
    {
        self::assertSame('product.created_at', $this->queryPath('createdAt', UntranslatedFilteredProduct::class));
    }

    private function queryPath(string $property, string $resourceClass): string
    {
        $tableMap = $this->createStub(TableMap::class);
        $tableMap->method('getName')->willReturn('product');

        $query = $this->createStub(ModelCriteria::class);
        $query->method('getTableMap')->willReturn($tableMap);

        return $this->filter(null)->queryPathOf($query, $property, ['resource_class' => $resourceClass]);
    }

    private function filter(?LoggerInterface $logger): object
    {
        return new class($logger) extends AbstractFilter {
            public function loggerInUse(): LoggerInterface
            {
                return $this->getLogger();
            }

            public function queryPathOf(ModelCriteria $query, string $property, array $context): string
            {
                return $this->getPropertyQueryPath($query, $property, $context);
            }

            protected function getLocalizedPropertyQueryPath(ModelCriteria $query, string $property, array $context): string
            {
                return 'localized:'.$property;
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

final class TranslatedFilteredProduct implements TranslatableResourceInterface
{
    public ?\DateTimeInterface $createdAt = null;

    public function setI18ns(array $i18ns): self
    {
        return $this;
    }

    public function addI18n(I18n $i18n, string $locale): self
    {
        return $this;
    }

    public function getI18ns(): I18nCollection
    {
        return new I18nCollection();
    }

    public static function getI18nResourceClass(): string
    {
        return I18n::class;
    }
}

final class UntranslatedFilteredProduct
{
}
