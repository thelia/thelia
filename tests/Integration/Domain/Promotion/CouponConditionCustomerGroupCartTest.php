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

namespace Thelia\Tests\Integration\Domain\Promotion;

use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\ForSomeCustomerGroups;
use Thelia\Condition\Operators;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Cart;
use Thelia\Model\CustomerGroupQuery;

/**
 * A promotion reserved to a customer group, evaluated on a cart the way the checkout does: the group of the
 * signed-in customer, or the default group for a visitor.
 */
final class CouponConditionCustomerGroupCartTest extends ActionIntegrationPromotionTestCase
{
    protected function tearDown(): void
    {
        // The next test must not start with this one's customer signed in.
        $this->getService(SecurityContext::class)->clearCustomerUser();

        parent::tearDown();
    }

    public function testAPromotionReservedToAGroupAppliesToItsMembers(): void
    {
        $group = $this->factory->customerGroup('pro');
        $this->signIn($group);
        $this->promotionWhen(Operators::IN, $group->getId());

        self::assertSame(5.0, $this->discountOfACartWithOneProduct());
    }

    public function testAPromotionReservedToAGroupDoesNotApplyToOtherGroups(): void
    {
        $this->signIn($this->factory->customerGroup('retail'));
        $this->promotionWhen(Operators::IN, $this->factory->customerGroup('pro')->getId());

        self::assertSame(0.0, $this->discountOfACartWithOneProduct());
    }

    public function testAPromotionReservedToTheDefaultGroupAppliesToAVisitor(): void
    {
        $this->promotionWhen(Operators::IN, CustomerGroupQuery::findDefault()->getId());

        self::assertSame(5.0, $this->discountOfACartWithOneProduct());
    }

    public function testAPromotionReservedToAnotherGroupDoesNotApplyToAVisitor(): void
    {
        $this->promotionWhen(Operators::IN, $this->factory->customerGroup('pro')->getId());

        self::assertSame(0.0, $this->discountOfACartWithOneProduct());
    }

    private function signIn(\Thelia\Model\CustomerGroup $group): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
        $this->getService(SecurityContext::class)->setCustomerUser($customer);
    }

    /**
     * The ids go through the serialized form the condition is stored in, posted as strings like the back-office does.
     */
    private function promotionWhen(string $operator, int $groupId): void
    {
        $conditionFactory = $this->getService(ConditionFactory::class);

        $conditions = new ConditionCollection();
        $conditions[] = $conditionFactory->build(
            'thelia.condition.for_some_customer_groups',
            [ForSomeCustomerGroups::CUSTOMER_GROUPS_LIST => $operator],
            [ForSomeCustomerGroups::CUSTOMER_GROUPS_LIST => [(string) $groupId]],
        );

        $this->automaticPromotion(conditions: $conditionFactory->serializeConditionCollection($conditions));
    }

    private function discountOfACartWithOneProduct(): float
    {
        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());

        return $this->cartDiscount($cart);
    }

    private function cartDiscount(Cart $cart): float
    {
        $cart->reload();

        return (float) $cart->getDiscount();
    }
}
