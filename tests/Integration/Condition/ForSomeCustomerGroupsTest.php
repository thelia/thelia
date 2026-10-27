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

namespace Thelia\Tests\Integration\Condition;

use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Exception\InvalidConditionValueException;
use Thelia\Condition\Implementation\ForSomeCustomerGroups;
use Thelia\Condition\Operators;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Lang;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * A visitor with no account belongs to the default customer group, so the condition is evaluated for a guest
 * instead of being unmatchable.
 */
final class ForSomeCustomerGroupsTest extends IntegrationTestCase
{
    private const PAYLOAD = '<svg onload=1>';

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    public function testACustomerOfAListedGroupMatches(): void
    {
        $group = $this->factory->customerGroup('pro');

        self::assertTrue($this->condition(Operators::IN, [$group->getId()], $this->customerIn($group))->isMatching());
    }

    public function testACustomerOfAnotherGroupDoesNotMatch(): void
    {
        $listed = $this->factory->customerGroup('pro');
        $other = $this->factory->customerGroup('retail');

        self::assertFalse($this->condition(Operators::IN, [$listed->getId()], $this->customerIn($other))->isMatching());
    }

    public function testTheNotInOperatorExcludesTheListedGroups(): void
    {
        $listed = $this->factory->customerGroup('pro');
        $other = $this->factory->customerGroup('retail');

        self::assertFalse($this->condition(Operators::OUT, [$listed->getId()], $this->customerIn($listed))->isMatching());
        self::assertTrue($this->condition(Operators::OUT, [$listed->getId()], $this->customerIn($other))->isMatching());
    }

    public function testAGuestIsInTheDefaultGroup(): void
    {
        $default = CustomerGroupQuery::findDefault();
        $other = $this->factory->customerGroup('pro');

        self::assertTrue($this->condition(Operators::IN, [$default->getId()], null)->isMatching());
        self::assertFalse($this->condition(Operators::IN, [$other->getId()], null)->isMatching());
        self::assertFalse($this->condition(Operators::OUT, [$default->getId()], null)->isMatching());
        self::assertTrue($this->condition(Operators::OUT, [$other->getId()], null)->isMatching());
    }

    public function testAGuestAccountPutInAGroupIsStillInTheDefaultGroup(): void
    {
        $default = CustomerGroupQuery::findDefault();
        $pro = $this->factory->customerGroup('pro');
        $guest = $this->customerIn($pro);
        $guest->setIsGuest(1)->save($this->getPropelConnection());

        self::assertFalse($this->condition(Operators::IN, [$pro->getId()], $guest)->isMatching());
        self::assertTrue($this->condition(Operators::IN, [$default->getId()], $guest)->isMatching());
    }

    public function testACustomerMovedToAnotherGroupDuringTheSessionMatchesTheNewOne(): void
    {
        $pro = $this->factory->customerGroup('pro');
        $retail = $this->factory->customerGroup('retail');
        // The session holds the customer as they signed in; the back office moves the account since.
        $sessionCopy = $this->customerIn($pro);
        CustomerQuery::create()->filterById($sessionCopy->getId())->update(['CustomerGroupId' => $retail->getId()], $this->getPropelConnection());

        self::assertTrue($this->condition(Operators::IN, [$retail->getId()], $sessionCopy)->isMatching());
        self::assertFalse($this->condition(Operators::IN, [$pro->getId()], $sessionCopy)->isMatching());
    }

    public function testNoGroupIsRefused(): void
    {
        $this->expectException(InvalidConditionValueException::class);

        $this->condition(Operators::IN, [], null);
    }

    public function testTheIdsPostedAsStringsAreStoredAsIntegers(): void
    {
        $group = $this->factory->customerGroup('pro');

        $condition = $this->condition(Operators::IN, [(string) $group->getId()], $this->customerIn($group));

        self::assertSame([ForSomeCustomerGroups::CUSTOMER_GROUPS_LIST => [$group->getId()]], $condition->getSerializableCondition()->values);
        self::assertTrue($condition->isMatching());
    }

    public function testAGroupTitleIsInertInTheSummary(): void
    {
        $group = $this->factory->customerGroup('pro', title: self::PAYLOAD);
        $group->setLocale(Lang::getDefaultLanguage()->getLocale())->setTitle(self::PAYLOAD)->save($this->getPropelConnection());

        $summary = $this->condition(Operators::IN, [$group->getId()], null)->getSummary();

        self::assertStringContainsString('&lt;svg', $summary);
        self::assertStringNotContainsString('<svg', $summary);
    }

    public function testAGroupCodeIsInertInTheSummaryWhenTheGroupHasNoTitle(): void
    {
        $group = $this->factory->customerGroup(self::PAYLOAD);
        $group->setLocale(Lang::getDefaultLanguage()->getLocale())->setTitle('')->save($this->getPropelConnection());

        $summary = $this->condition(Operators::IN, [$group->getId()], null)->getSummary();

        self::assertStringContainsString('&lt;svg', $summary);
        self::assertStringNotContainsString('<svg', $summary);
    }

    public function testTheConditionSurvivesItsSerialization(): void
    {
        $group = $this->factory->customerGroup('pro');
        $factory = $this->getService(ConditionFactory::class);

        $conditions = new ConditionCollection();
        $conditions[] = $factory->build(
            'thelia.condition.for_some_customer_groups',
            [ForSomeCustomerGroups::CUSTOMER_GROUPS_LIST => Operators::OUT],
            [ForSomeCustomerGroups::CUSTOMER_GROUPS_LIST => [(string) $group->getId()]],
        );

        $rebuilt = $factory->unserializeConditionCollection($factory->serializeConditionCollection($conditions));

        self::assertCount(1, $rebuilt);
        $condition = $rebuilt[0];
        self::assertInstanceOf(ForSomeCustomerGroups::class, $condition);
        self::assertSame('thelia.condition.for_some_customer_groups', $condition->getServiceId());
        self::assertSame(
            [ForSomeCustomerGroups::CUSTOMER_GROUPS_LIST => Operators::OUT],
            $condition->getSerializableCondition()->operators,
        );
        self::assertSame(
            [ForSomeCustomerGroups::CUSTOMER_GROUPS_LIST => [$group->getId()]],
            $condition->getSerializableCondition()->values,
        );
    }

    public function testTheConditionRefusesToBeBuiltWithoutTheSharedResolver(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('needs the shared CustomerGroupResolver');

        new ForSomeCustomerGroups($this->createMock(FacadeInterface::class));
    }

    /**
     * @param list<int|string> $groupIds
     */
    private function condition(string $operator, array $groupIds, ?Customer $customer): ForSomeCustomerGroups
    {
        $facade = $this->createMock(FacadeInterface::class);
        $facade->method('getTranslator')->willReturn(Translator::getInstance());
        $facade->method('getConditionEvaluator')->willReturn(new ConditionEvaluator());
        $facade->method('getCustomer')->willReturn($customer);
        $facade->method('getRequest')->willReturn(
            static::getContainer()->get('request_stack')->getCurrentRequest(),
        );

        $probe = new CatalogActivityProbe();

        return (new ForSomeCustomerGroups($facade, new CustomerGroupResolver($probe)))->setValidatorsFromForm(
            [ForSomeCustomerGroups::CUSTOMER_GROUPS_LIST => $operator],
            [ForSomeCustomerGroups::CUSTOMER_GROUPS_LIST => $groupIds],
        );
    }

    private function customerIn(CustomerGroup $group): Customer
    {
        return $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
    }
}
