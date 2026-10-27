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

namespace Thelia\Tests\Integration\Domain\Customer;

use Thelia\Domain\Catalog\Category\CategoryVisibility;
use Thelia\Domain\Customer\Service\CustomerGroupPaymentPolicy;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContext;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * Every reader of the group of a customer - the prices, the payment modules, the
 * categories - gets the same answer from {@see CustomerGroupResolver}: a guest account
 * is in the default group whatever group its row names, and a signed-in customer is in
 * the group the database says, not the one the session copy was taken with.
 */
final class CustomerGroupResolutionTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private FixtureFactory $factory;

    private Currency $currency;

    private ProductSaleElements $saleElement;

    private Category $proCategory;

    private Category $otherCategory;

    private Module $proPayment;

    private CustomerGroup $pro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->currency = $this->factory->currency();
        $this->otherCategory = $this->factory->category();
        $product = $this->factory->product($this->otherCategory, $this->factory->taxRule(), $this->currency, ['basePrice' => 100.0]);
        $this->saleElement = $product->getProductSaleElementss()->getFirst();
        $this->proCategory = $this->factory->category();
        $this->proPayment = $this->paymentModule();
        $this->pro = $this->factory->customerGroup(
            'pro',
            discount: 20.0,
            paymentModuleIds: [(int) $this->proPayment->getId()],
            categoryIds: [(int) $this->proCategory->getId()],
        );
        $this->forgetTheRequest();
    }

    protected function tearDown(): void
    {
        $this->forgetTheRequest();

        parent::tearDown();
    }

    public function testAMemberOfTheGroupGetsWhatTheGroupGets(): void
    {
        $member = $this->customerIn($this->pro);

        self::assertSame(80.0, $this->chargedPriceFor($member));
        self::assertTrue($this->paymentPolicy()->isAllowed((int) $this->proPayment->getId(), $member));
        self::assertFalse($this->paymentPolicy()->isAllowed((int) $this->otherPayment()->getId(), $member), 'the list of the group');
        self::assertNotContains((int) $this->proCategory->getId(), $this->categoryVisibility()->closedCategoryIdsFor($member));
        self::assertContains((int) $this->otherCategory->getId(), $this->categoryVisibility()->closedCategoryIdsFor($member), 'the catalog of the group');
    }

    public function testAGuestAccountPutInAGroupIsTreatedAsTheDefaultGroupEverywhere(): void
    {
        $guest = $this->customerIn($this->pro);
        $guest->setIsGuest(1)->save($this->getPropelConnection());

        self::assertSame((int) CustomerGroupQuery::findDefault()->getId(), $this->resolver()->groupIdOf($guest));
        self::assertSame(100.0, $this->chargedPriceFor($guest), 'priced as the default group');
        self::assertTrue($this->paymentPolicy()->isAllowed((int) $this->otherPayment()->getId(), $guest), 'paid as the default group, which has no list');
        self::assertSame([], $this->categoryVisibility()->closedCategoryIdsFor($guest), 'shown the catalog of the default group, which has no list');
    }

    public function testACustomerMovedToAnotherGroupDuringTheSessionIsInTheNewOneAtOnce(): void
    {
        $sessionCopy = $this->customerIn($this->pro);
        self::assertSame(80.0, $this->chargedPriceFor($sessionCopy));
        $this->forgetTheRequest();

        // The back office moves the account; the session still holds the copy taken at sign-in.
        $retail = $this->factory->customerGroup('retail', discount: 10.0);
        CustomerQuery::create()->filterById($sessionCopy->getId())->update(['CustomerGroupId' => $retail->getId()], $this->getPropelConnection());

        self::assertSame((int) $this->pro->getId(), (int) $sessionCopy->getCustomerGroupId(), 'the copy is stale');
        self::assertSame((int) $retail->getId(), $this->resolver()->groupIdOf($sessionCopy));
        self::assertSame(90.0, $this->chargedPriceFor($sessionCopy));
        self::assertTrue($this->paymentPolicy()->isAllowed((int) $this->otherPayment()->getId(), $sessionCopy), 'the list of pro no longer applies');
        self::assertSame([], $this->categoryVisibility()->closedCategoryIdsFor($sessionCopy), 'retail has no list');
    }

    public function testTheGroupOfACustomerIsReadOncePerRequest(): void
    {
        $member = $this->customerIn($this->pro);
        $resolver = $this->resolver();

        self::assertSame((int) $this->pro->getId(), $resolver->groupIdOf($member));
        CustomerQuery::create()->filterById($member->getId())->update(['CustomerGroupId' => CustomerGroupQuery::findDefault()->getId()], $this->getPropelConnection());

        self::assertSame((int) $this->pro->getId(), $resolver->groupIdOf($member), 'kept for the request');
        $resolver->reset();
        self::assertSame((int) CustomerGroupQuery::findDefault()->getId(), $resolver->groupIdOf($member));
    }

    public function testWhileEveryGroupShowsPricesTheSameWayNobodyIsAskedForTheirGroup(): void
    {
        $member = $this->customerIn($this->pro);
        $context = $this->getService(CustomerPriceContext::class);
        $context->displaysTaxedPrices(null);

        $statements = $this->recordSqlQueries(static function () use ($context, $member): void {
            self::assertTrue($context->displaysTaxedPrices($member));
        });

        self::assertSame([], $statements);
    }

    public function testAGroupShowingUntaxedPricesShowsThemToItsMembersOnly(): void
    {
        $untaxed = $this->factory->customerGroup('untaxed', displayTaxedPrice: false);
        $member = $this->customerIn($untaxed);
        $guest = $this->customerIn($untaxed);
        $guest->setIsGuest(1)->save($this->getPropelConnection());
        $context = $this->getService(CustomerPriceContext::class);

        self::assertFalse($context->displaysTaxedPrices($member));
        self::assertTrue($context->displaysTaxedPrices($guest));
        self::assertTrue($context->displaysTaxedPrices(null));
    }

    private function chargedPriceFor(Customer $customer): float
    {
        $prices = $this->getService(CustomerPriceContext::class)->resolve([(int) $this->saleElement->getId()], $this->currency, $customer);

        self::assertArrayHasKey((int) $this->saleElement->getId(), $prices);

        return round($prices[(int) $this->saleElement->getId()]->chargedPrice(), 6);
    }

    private function customerIn(CustomerGroup $group): Customer
    {
        return $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
    }

    private function paymentModule(): Module
    {
        $module = ModuleQuery::create()->findOneByCode('Cheque');
        self::assertNotNull($module);

        return $module;
    }

    private function otherPayment(): Module
    {
        $module = ModuleQuery::create()->filterByType(3)->filterById($this->proPayment->getId(), \Propel\Runtime\ActiveQuery\Criteria::NOT_EQUAL)->findOne();
        self::assertNotNull($module);

        return $module;
    }

    private function resolver(): CustomerGroupResolver
    {
        return $this->getService(CustomerGroupResolver::class);
    }

    private function paymentPolicy(): CustomerGroupPaymentPolicy
    {
        return $this->getService(CustomerGroupPaymentPolicy::class);
    }

    private function categoryVisibility(): CategoryVisibility
    {
        return $this->getService(CategoryVisibility::class);
    }

    /**
     * What a new request starts from: nothing remembered of the previous one.
     */
    private function forgetTheRequest(): void
    {
        $this->getService('services_resetter')->reset();
    }
}
