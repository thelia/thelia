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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerPersonalDataExportEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Lang;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The export of an account says which group the customer sits in, and the export of an order what the order
 * froze of it: the group and the two rates already included in the prices.
 */
final class CustomerGroupPersonalDataTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    public function testTheAccountExportNamesTheGroup(): void
    {
        $group = $this->factory->customerGroup('pro', title: 'Professionals');
        $customer = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
        $customer->setLangId(Lang::getDefaultLanguage()->getId())->save();

        $account = $this->export($customer)['customer'];

        self::assertSame('pro', $account['customer_group']['code']);
        self::assertSame('Professionals', $account['customer_group']['title']);
    }

    public function testTheOrderExportCarriesTheFrozenGroupAndRates(): void
    {
        $group = $this->factory->customerGroup('pro', 15.0);
        $customer = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
        $order = $this->factory->order($customer);
        $order
            ->setCustomerGroupId($group->getId())
            ->setCustomerGroupDiscountRate('15.000000')
            ->setCustomerDiscountRate('10.000000')
            ->save($this->getPropelConnection());

        $exported = $this->export($customer)['orders'][0];

        self::assertSame('pro', $exported['customer_group']);
        self::assertSame(15.0, (float) $exported['customer_group_discount_rate']);
        self::assertSame(10.0, (float) $exported['customer_discount_rate']);
    }

    public function testTheOrderExportOfAnOrderWithoutGroupHasNoGroup(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->factory->order($customer);

        $exported = $this->export($customer)['orders'][0];

        self::assertNull($exported['customer_group']);
    }

    /**
     * @return array<string, mixed>
     */
    private function export(\Thelia\Model\Customer $customer): array
    {
        $event = new CustomerPersonalDataExportEvent($customer);
        $this->getService(EventDispatcherInterface::class)->dispatch($event, TheliaEvents::CUSTOMER_PERSONAL_DATA_EXPORT);

        return $event->getPersonalData();
    }
}
