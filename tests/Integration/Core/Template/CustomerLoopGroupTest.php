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

namespace Thelia\Tests\Integration\Core\Template;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;

final class CustomerLoopGroupTest extends IntegrationTestCase
{
    public function testTheRowCarriesTheGroupOfTheCustomer(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup('pro', title: 'Professionals');
        $customer = $factory->customer($factory->customerTitle(), ['customerGroup' => $group]);

        $rows = $this->rows(['id' => $customer->getId()]);

        self::assertCount(1, $rows);
        self::assertSame($group->getId(), $rows[0]['CUSTOMER_GROUP_ID']);
        self::assertSame('pro', $rows[0]['CUSTOMER_GROUP_CODE']);
        self::assertSame('Professionals', $rows[0]['CUSTOMER_GROUP_TITLE']);
    }

    public function testTheCustomerGroupArgumentFiltersTheCustomers(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup('pro');
        $inGroup = $factory->customer($factory->customerTitle(), ['customerGroup' => $group]);
        $outOfGroup = $factory->customer($factory->customerTitle());

        $ids = array_column($this->rows(['id' => $inGroup->getId().','.$outOfGroup->getId(), 'customer_group' => $group->getId()]), 'ID');

        self::assertSame([$inGroup->getId()], $ids);
    }

    /**
     * A console command or a worker runs the loop with no request: the group is titled in the
     * default language of the shop, and nothing asks a session that is not there.
     */
    public function testTheLoopRunsWithNoRequestAndWithARequestWithoutASession(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup('pro', title: 'Professionals');
        $customer = $factory->customer($factory->customerTitle(), ['customerGroup' => $group]);
        $requestStack = $this->getService('request_stack');
        $requests = [];

        while (null !== $request = $requestStack->pop()) {
            $requests[] = $request;
        }

        try {
            $withNoRequest = $this->rows(['id' => $customer->getId()]);

            $requestStack->push(Request::create('http://localhost'));
            $withNoSession = $this->rows(['id' => $customer->getId()]);
        } finally {
            while (null !== $requestStack->pop()) {
                // Whatever the test pushed is dropped, the requests of the case are put back.
            }
            foreach (array_reverse($requests) as $request) {
                $requestStack->push($request);
            }
        }

        foreach ([$withNoRequest, $withNoSession] as $rows) {
            self::assertCount(1, $rows);
            self::assertSame('pro', $rows[0]['CUSTOMER_GROUP_CODE']);
        }
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<array<string, mixed>>
     */
    private function rows(array $arguments): array
    {
        $lang = LangQuery::create()->filterByByDefault(1)->findOne() ?? LangQuery::create()->findOne();

        $result = $this->getService(LoopExecutor::class)->execute('customer', ['current' => false, 'lang' => $lang->getId()] + $arguments);

        $rows = [];
        foreach ($result as $row) {
            $rows[] = $row->getVarVal();
        }

        return $rows;
    }
}
