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

use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Model\Customer;
use Thelia\Model\Sale;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;

/**
 * The sale loop, as a Smarty template reads it: the address of the operation's
 * page, who it is open to, and how long it still has to run.
 *
 * A reserved operation is only part of the front for the customers it names —
 * the back office keeps listing every one of them, drafts included, because
 * that is where they are set up.
 */
final class SaleLoopTest extends IntegrationTestCase
{
    use LogsInAsCustomer;

    public function testTheOperationHandsOutTheAddressOfItsPage(): void
    {
        $sale = $this->createFixtureFactory()->sale([
            'active' => true,
            'title' => 'Winter drop',
        ]);

        $row = $this->saleRow($sale);

        self::assertStringEndsWith('winter-drop.html', (string) $row['URL']);
    }

    public function testTheAudienceSettingsTravelWithTheOperation(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $this->newCustomer();
        $sale = $factory->sale([
            'active' => true,
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'hideProducts' => true,
        ]);
        $factory->saleCustomer($sale, $customer);
        $this->loginAsCustomerInSession($customer);

        $row = $this->saleRow($sale);

        self::assertSame(Sale::AUDIENCE_MODE_CUSTOMERS, (int) $row['AUDIENCE_MODE']);
        self::assertSame(1, (int) $row['HIDE_PRODUCTS']);
    }

    public function testTheCountdownIsAnsweredAsANumberOfSeconds(): void
    {
        $sale = $this->createFixtureFactory()->sale([
            'active' => true,
            'countdownMode' => Sale::COUNTDOWN_MODE_FROM_OPENING,
            'startDate' => new \DateTime('-1 day'),
            // Truncated to the second: MariaDB rounds a fractional DATETIME up,
            // which pushed the remaining seconds to 7201 on an unlucky run.
            'endDate' => new \DateTime((new \DateTime('+2 hours'))->format('Y-m-d H:i:s')),
        ]);

        $row = $this->saleRow($sale);

        self::assertSame(Sale::COUNTDOWN_MODE_FROM_OPENING, (int) $row['COUNTDOWN_MODE']);
        self::assertSame(1, (int) $row['SHOULD_DISPLAY_COUNTDOWN']);
        self::assertGreaterThan(7100, (int) $row['COUNTDOWN_REMAINING_SECONDS']);
        self::assertLessThanOrEqual(7200, (int) $row['COUNTDOWN_REMAINING_SECONDS']);
    }

    public function testAnOperationWithNoCountdownAnswersNoRemainingSeconds(): void
    {
        $sale = $this->createFixtureFactory()->sale([
            'active' => true,
            'countdownMode' => Sale::COUNTDOWN_MODE_NONE,
            'endDate' => new \DateTime('+2 hours'),
        ]);

        $row = $this->saleRow($sale);

        self::assertSame(0, (int) $row['SHOULD_DISPLAY_COUNTDOWN']);
        // LoopResultRow::set() turns a null into an empty string, the way every
        // other optional output of every other loop is answered.
        self::assertSame('', $row['COUNTDOWN_REMAINING_SECONDS']);
    }

    public function testTheLeadTimeTravelsWithTheOperation(): void
    {
        $sale = $this->createFixtureFactory()->sale([
            'active' => true,
            'countdownMode' => Sale::COUNTDOWN_MODE_LEAD_HOURS,
            'countdownLeadHours' => 3,
            'endDate' => new \DateTime('+10 hours'),
        ]);

        $row = $this->saleRow($sale);

        self::assertSame(3, (int) $row['COUNTDOWN_LEAD_HOURS']);
        self::assertSame(
            0,
            (int) $row['SHOULD_DISPLAY_COUNTDOWN'],
            'Ten hours out, a countdown that starts three hours before the end is not due.',
        );
    }

    public function testAVisitorDoesNotListAReservedOperation(): void
    {
        $sale = $this->reservedSaleFor($this->newCustomer());

        self::assertNull($this->saleRow($sale));
    }

    public function testACustomerTheOperationDoesNotNameDoesNotListItEither(): void
    {
        $sale = $this->reservedSaleFor($this->newCustomer());
        $this->loginAsCustomerInSession($this->newCustomer());

        self::assertNull($this->saleRow($sale));
    }

    public function testTheCustomerTheOperationNamesListsIt(): void
    {
        $customer = $this->newCustomer();
        $sale = $this->reservedSaleFor($customer);
        $this->loginAsCustomerInSession($customer);

        self::assertNotNull($this->saleRow($sale));
    }

    public function testTheBackOfficeListsEveryOperation(): void
    {
        $sale = $this->reservedSaleFor($this->newCustomer());

        self::assertNotNull($this->saleRow($sale, ['backend_context' => 1]));
    }

    public function testAPublicOperationIsListedByEverybody(): void
    {
        $sale = $this->createFixtureFactory()->sale([
            'active' => true,
            'audienceMode' => Sale::AUDIENCE_MODE_PUBLIC,
        ]);

        self::assertNotNull($this->saleRow($sale));
    }

    private function reservedSaleFor(Customer $customer): Sale
    {
        $factory = $this->createFixtureFactory();
        $sale = $factory->sale([
            'active' => true,
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
        ]);
        $factory->saleCustomer($sale, $customer);

        return $sale;
    }

    private function newCustomer(): Customer
    {
        $factory = $this->createFixtureFactory();

        return $factory->customer($factory->customerTitle());
    }

    /**
     * @return array<string, mixed>|null
     */
    private function saleRow(Sale $sale, array $arguments = []): ?array
    {
        $result = $this->getService(LoopExecutor::class)->execute('sale', $arguments + ['id' => $sale->getId()]);

        foreach ($result as $row) {
            return $row->getVarVal();
        }

        return null;
    }
}
