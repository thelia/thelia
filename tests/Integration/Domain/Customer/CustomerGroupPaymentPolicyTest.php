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

use Thelia\Domain\Checkout\Exception\InvalidPaymentException;
use Thelia\Domain\Customer\Service\CustomerGroupLinks;
use Thelia\Domain\Customer\Service\CustomerGroupPaymentPolicy;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Model\CustomerGroupPaymentModule;
use Thelia\Model\CustomerGroupPaymentModuleQuery;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * Each group lists the payment modules it may pay with: an empty list means every module, a
 * list that is not empty means those modules only, and the list of one group never opens nor
 * closes anything to another. A visitor with no account belongs to the default group.
 */
final class CustomerGroupPaymentPolicyTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private FixtureFactory $factory;

    private CustomerGroupPaymentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->policy = $this->getService(CustomerGroupPaymentPolicy::class);
        $this->getService('services_resetter')->reset();
    }

    protected function tearDown(): void
    {
        $this->getService('services_resetter')->reset();

        parent::tearDown();
    }

    public function testWhileNoGroupHasAListEveryModuleIsOpenToEveryone(): void
    {
        [$cheque] = $this->twoPaymentModules();
        $customer = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $this->factory->customerGroup('pro')]);

        self::assertTrue($this->policy->isAllowed((int) $cheque->getId(), $customer));
        self::assertTrue($this->policy->isAllowed((int) $cheque->getId(), null));
    }

    public function testAGroupThatListsOneModuleMayPayWithItAndWithNoOther(): void
    {
        [$cheque, $other] = $this->twoPaymentModules();
        $pro = $this->factory->customerGroup('pro', paymentModuleIds: [(int) $cheque->getId()]);
        $professional = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $pro]);

        self::assertTrue($this->policy->isAllowed((int) $cheque->getId(), $professional));
        self::assertFalse($this->policy->isAllowed((int) $other->getId(), $professional));
    }

    public function testTheListOfAGroupClosesNothingToTheDefaultGroupNorToAVisitor(): void
    {
        [$cheque, $other] = $this->twoPaymentModules();
        $this->factory->customerGroup('pro', paymentModuleIds: [(int) $cheque->getId()]);
        $consumer = $this->factory->customer($this->factory->customerTitle());

        self::assertTrue($this->policy->isAllowed((int) $cheque->getId(), $consumer));
        self::assertTrue($this->policy->isAllowed((int) $other->getId(), $consumer), 'The default group has no list: every module.');
        self::assertTrue($this->policy->isAllowed((int) $other->getId(), null), 'A visitor is the default group.');
    }

    public function testAVisitorFollowsTheListOfTheDefaultGroupWhenItHasOne(): void
    {
        [$cheque, $other] = $this->twoPaymentModules();
        (new CustomerGroupPaymentModule())
            ->setCustomerGroupId((int) CustomerGroupQuery::findDefault()->getId())
            ->setModuleId((int) $cheque->getId())
            ->save();
        CustomerGroupQuery::findDefault()->setRestrictsPaymentModules(1)->save();
        $this->getService('services_resetter')->reset();

        self::assertTrue($this->policy->isAllowed((int) $cheque->getId(), null));
        self::assertFalse($this->policy->isAllowed((int) $other->getId(), null));
    }

    public function testTheGroupIsReadFromTheDatabaseNotFromTheObjectTheCallerHolds(): void
    {
        [$cheque, $other] = $this->twoPaymentModules();
        $pro = $this->factory->customerGroup('pro', paymentModuleIds: [(int) $cheque->getId()]);
        $customer = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $pro]);

        // The object the session holds is a snapshot; the account is moved afterwards.
        $snapshot = clone $customer;
        $this->factory->assignCustomerGroup($customer, $this->factory->customerGroup('other'));

        self::assertTrue($this->policy->isAllowed((int) $other->getId(), $snapshot), 'The group it was moved to has no list.');
    }

    public function testTheListsAreReadInOneStatementWhateverTheModulesAndCustomersAsked(): void
    {
        [$cheque, $other] = $this->twoPaymentModules();
        $pro = $this->factory->customerGroup('pro', paymentModuleIds: [(int) $cheque->getId()]);
        $professional = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $pro]);
        $consumer = $this->factory->customer($this->factory->customerTitle());
        $resolver = $this->getService(CustomerGroupResolver::class);
        $resolver->groupIdOf($professional);
        $resolver->groupIdOf($consumer);

        $statements = $this->recordSqlQueries(function () use ($cheque, $other, $professional, $consumer): void {
            for ($i = 0; $i < 20; ++$i) {
                foreach ([$cheque, $other] as $module) {
                    $this->policy->isAllowed((int) $module->getId(), $professional);
                    $this->policy->isAllowed((int) $module->getId(), $consumer);
                }
            }
        });

        self::assertCount(1, $statements, implode("\n", $statements));
    }

    public function testDeletingTheOnlyModuleAGroupListsLeavesItWithNoModuleInsteadOfEveryModule(): void
    {
        [$cheque, $other] = $this->twoPaymentModules();
        $listed = $this->factory->paymentModule();
        $pro = $this->factory->customerGroup('pro', paymentModuleIds: [(int) $listed->getId()]);
        $professional = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $pro]);

        // The administrator deletes the module: the database drops the link and nothing else.
        ModuleQuery::create()->filterById($listed->getId())->delete($this->getPropelConnection());
        self::assertSame(0, CustomerGroupPaymentModuleQuery::create()->filterByCustomerGroupId($pro->getId())->count(), 'The list is empty.');
        self::assertSame(1, CustomerGroupQuery::create()->findPk($pro->getId())->getRestrictsPaymentModules(), 'The flag is still there.');
        $this->getService('services_resetter')->reset();

        self::assertFalse($this->policy->isAllowed((int) $cheque->getId(), $professional));
        self::assertFalse($this->policy->isAllowed((int) $other->getId(), $professional));
        self::assertTrue($this->policy->isAllowed((int) $other->getId(), null), 'The default group is not concerned.');
    }

    public function testEmptyingTheListWithTheWriterLiftsTheRestriction(): void
    {
        [$cheque] = $this->twoPaymentModules();
        $listed = $this->factory->paymentModule();
        $pro = $this->factory->customerGroup('pro', paymentModuleIds: [(int) $listed->getId()]);
        $professional = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $pro]);
        ModuleQuery::create()->filterById($listed->getId())->delete($this->getPropelConnection());

        $this->getService(CustomerGroupLinks::class)->replace($pro, null, [], $this->getPropelConnection());
        $this->getService('services_resetter')->reset();

        self::assertTrue($this->policy->isAllowed((int) $cheque->getId(), $professional));
    }

    public function testAListWithNoFlagRestrictsNothing(): void
    {
        [$cheque, $other] = $this->twoPaymentModules();
        $pro = $this->factory->customerGroup('pro', paymentModuleIds: [(int) $cheque->getId()]);
        $pro->setRestrictsPaymentModules(0)->save($this->getPropelConnection());
        $professional = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $pro]);
        $this->getService('services_resetter')->reset();

        self::assertTrue($this->policy->isAllowed((int) $other->getId(), $professional));
    }

    public function testTheRefusalIsAnInvalidPaymentException(): void
    {
        [$cheque, $other] = $this->twoPaymentModules();
        $pro = $this->factory->customerGroup('pro', paymentModuleIds: [(int) $cheque->getId()]);
        $cart = $this->factory->cart($this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $pro]));

        $this->expectException(InvalidPaymentException::class);
        $this->policy->refuseUnlessAllowedForCart((int) $other->getId(), $cart);
    }

    /**
     * @return array{0: Module, 1: Module}
     */
    private function twoPaymentModules(): array
    {
        $modules = ModuleQuery::create()->filterByType(3)->filterByActivate(1)->orderById()->limit(2)->find()->getData();

        if (2 !== \count($modules)) {
            throw new \RuntimeException('Two payment modules are needed — run bin/test-prepare.');
        }

        return [$modules[0], $modules[1]];
    }
}
