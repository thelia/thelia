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

namespace Thelia\Tests\Unit\Domain\Catalog\Product;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\Compiler\RegisterAutoconfigureAttributesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Catalog\Product\ProductVisibilityRuleInterface;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Domain\Sale\ReservedSaleProductRule;
use Thelia\Domain\Sale\SaleAudienceChecker;
use Thelia\Model\Customer;

/**
 * The private drops of the reserved operations come first, the rules of the
 * modules are joined to them with AND, and a shop with neither reads the catalog
 * as before.
 */
final class ProductVisibilityTest extends TestCase
{
    /**
     * Without a running operation nor a rule, no criterion and no read of the
     * visitor: the cost of a shop that uses neither does not change.
     */
    public function testWithoutReservedOperationNorRuleNothingIsNarrowed(): void
    {
        $currentCustomerProvider = $this->createMock(CurrentCustomerProvider::class);
        $currentCustomerProvider->expects(self::never())->method('getCurrentCustomer');

        $visibility = new ProductVisibility($this->reservedSaleRule(null), $currentCustomerProvider, []);

        self::assertNull($visibility->visibleProductClause('product.id'));
        self::assertFalse($visibility->hasModuleRules());
    }

    /**
     * The data access cache asks on every catalog read whether a module rule is
     * declared: the answer is counted, the rules themselves are not built for it.
     */
    public function testWhetherAModuleRuleIsDeclaredIsAnsweredWithoutBuildingIt(): void
    {
        $rules = new RewindableGenerator(static function (): \Generator {
            throw new \LogicException('A module rule was built to be counted.');
            yield;
        }, 1);

        $visibility = new ProductVisibility($this->reservedSaleRule(null), $this->createStub(CurrentCustomerProvider::class), $rules);

        self::assertTrue($visibility->hasModuleRules());
        self::assertFalse(
            (new ProductVisibility($this->reservedSaleRule(null), $this->createStub(CurrentCustomerProvider::class), new RewindableGenerator(static fn (): \Generator => yield from [], 0)))->hasModuleRules(),
        );
    }

    public function testARuleThatHidesNothingAddsNothing(): void
    {
        self::assertNull($this->visibility(null, [$this->rule(null)])->visibleProductClause('product.id'));
    }

    public function testEveryRuleMustLetTheProductThrough(): void
    {
        $visibility = $this->visibility(null, [
            $this->rule('%s NOT IN (1, 2)'),
            $this->rule(null),
            $this->rule('%s <> 3'),
        ]);

        self::assertTrue($visibility->hasModuleRules());
        self::assertSame(
            '((product_sale_elements.product_id NOT IN (1, 2)) AND (product_sale_elements.product_id <> 3))',
            $visibility->visibleProductClause('product_sale_elements.product_id'),
        );
    }

    /**
     * A rule that answers an empty clause is a mistake of its module: it is named,
     * and the catalog is not opened in its place.
     */
    public function testARuleAnsweringAnEmptyClauseIsNamed(): void
    {
        $visibility = $this->visibility(null, [new AnswersAnEmptyClause()]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(AnswersAnEmptyClause::class.' returned an empty clause');

        $visibility->visibleProductClause('product.id');
    }

    /**
     * The clause of the reserved operations is kept as it is, and comes first: a
     * shop with no module rule reads exactly the statement it read before.
     */
    public function testTheReservedOperationsComeFirstAndTheRulesAreJoinedToThem(): void
    {
        $reservedSaleClause = 'NOT EXISTS (SELECT 1 FROM `sale_product` WHERE `sale_product`.`product_id` = product.id AND `sale_product`.`sale_id` IN (7))';

        self::assertSame($reservedSaleClause, $this->visibility($reservedSaleClause, [])->visibleProductClause('product.id'));
        self::assertSame(
            '('.$reservedSaleClause.' AND (product.id <> 3))',
            $this->visibility($reservedSaleClause, [$this->rule('%s <> 3')])->visibleProductClause('product.id'),
        );
    }

    /**
     * A rule never looks for the visitor itself: it is handed the customer the
     * reserved operations are resolved for, or null for a visitor nobody knows.
     */
    public function testEveryRuleIsHandedTheVisitorOfTheReservedOperations(): void
    {
        $customer = $this->createStub(Customer::class);
        $rule = $this->createMock(ProductVisibilityRuleInterface::class);
        $rule->expects(self::once())->method('visibleProductClause')->with('product.id', self::identicalTo($customer))->willReturn(null);

        $this->visibility(null, [$rule], $customer)->visibleProductClause('product.id');

        $anonymousRule = $this->createMock(ProductVisibilityRuleInterface::class);
        $anonymousRule->expects(self::once())->method('visibleProductClause')->with('product.id', null)->willReturn(null);

        $this->visibility(null, [$anonymousRule], null)->visibleProductClause('product.id');
    }

    /**
     * A module declares its rule by implementing the interface, and nothing else:
     * the tag and the iterator bring it to the visibility, as the kernel builds it.
     * The rule of the reserved operations is not one of them, or every shop would
     * count as running a module rule.
     */
    public function testARuleDeclaredByItsInterfaceAloneReachesTheVisibility(): void
    {
        $withoutModuleRule = $this->compiledVisibility([]);

        self::assertFalse($withoutModuleRule->hasModuleRules());
        self::assertNull($withoutModuleRule->visibleProductClause('product.id'));

        $withModuleRule = $this->compiledVisibility([HidesProductThree::class]);

        self::assertTrue($withModuleRule->hasModuleRules());
        self::assertSame('(product.id <> 3)', $withModuleRule->visibleProductClause('product.id'));
    }

    /**
     * @param list<class-string<ProductVisibilityRuleInterface>> $moduleRuleClasses
     */
    private function compiledVisibility(array $moduleRuleClasses): ProductVisibility
    {
        $container = new ContainerBuilder();
        // What the kernel does for every interface it finds under core/lib.
        (new RegisterAutoconfigureAttributesPass())->processClass($container, new \ReflectionClass(ProductVisibilityRuleInterface::class));
        $container->register(SaleAudienceChecker::class)->setSynthetic(true);
        $container->register(CurrentCustomerProvider::class)->setSynthetic(true);
        $container->register(ReservedSaleProductRule::class)->setAutowired(true)->setAutoconfigured(true);

        foreach ($moduleRuleClasses as $moduleRuleClass) {
            $container->register($moduleRuleClass)->setAutoconfigured(true);
        }

        $container->register(ProductVisibility::class)->setAutowired(true)->setAutoconfigured(true)->setPublic(true);
        $container->compile();

        $saleAudienceChecker = $this->createStub(SaleAudienceChecker::class);
        $saleAudienceChecker->method('hasActiveReservedSale')->willReturn(false);
        $container->set(SaleAudienceChecker::class, $saleAudienceChecker);
        $container->set(CurrentCustomerProvider::class, $this->createStub(CurrentCustomerProvider::class));

        /** @var ProductVisibility $visibility */
        $visibility = $container->get(ProductVisibility::class);

        return $visibility;
    }

    /**
     * @param list<ProductVisibilityRuleInterface> $moduleRules
     */
    private function visibility(?string $reservedSaleClause, array $moduleRules, ?Customer $customer = null): ProductVisibility
    {
        $currentCustomerProvider = $this->createStub(CurrentCustomerProvider::class);
        $currentCustomerProvider->method('getCurrentCustomer')->willReturn($customer);

        return new ProductVisibility($this->reservedSaleRule($reservedSaleClause), $currentCustomerProvider, $moduleRules);
    }

    private function reservedSaleRule(?string $clause): ReservedSaleProductRule
    {
        $reservedSaleRule = $this->createStub(ReservedSaleProductRule::class);
        $reservedSaleRule->method('visibleProductClause')->willReturn($clause);

        return $reservedSaleRule;
    }

    private function rule(?string $clause): ProductVisibilityRuleInterface
    {
        return new readonly class($clause) implements ProductVisibilityRuleInterface {
            public function __construct(private ?string $clause)
            {
            }

            public function visibleProductClause(string $productIdColumn, ?Customer $customer): ?string
            {
                return null === $this->clause ? null : \sprintf($this->clause, $productIdColumn);
            }
        };
    }
}

final class HidesProductThree implements ProductVisibilityRuleInterface
{
    public function visibleProductClause(string $productIdColumn, ?Customer $customer): ?string
    {
        return \sprintf('%s <> 3', $productIdColumn);
    }
}

final class AnswersAnEmptyClause implements ProductVisibilityRuleInterface
{
    public function visibleProductClause(string $productIdColumn, ?Customer $customer): ?string
    {
        return ' ';
    }
}
