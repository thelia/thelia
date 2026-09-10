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

namespace Thelia\Tests\Api\Front;

use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\Sale;
use Thelia\Model\TaxRule;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The front read of a sale operation: what a theme needs to build the page of an
 * operation and its countdown, and nothing else.
 *
 * "Nothing else" is the load-bearing half: the customers a reserved operation is
 * open to are the operation's private guest list, and no front group may carry
 * them.
 */
final class SaleApiTest extends ApiTestCase
{
    use RecordsSqlQueries;

    private Currency $currency;
    private Category $category;
    private TaxRule $taxRule;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $this->currency = $factory->currency();
        $this->category = $factory->category();
        $this->taxRule = $factory->taxRule();
    }

    public function testAPublicOperationIsReadableByAnybody(): void
    {
        $product = $this->catalogProduct();
        $sale = $this->runningSale(['audienceMode' => Sale::AUDIENCE_MODE_PUBLIC]);
        $this->createFixtureFactory()->saleProduct($sale, $product);

        $member = $this->memberOrNull($this->readJson('/api/front/sales'), $sale->getId());

        self::assertNotNull($member);
        self::assertTrue($member['active']);
        self::assertSame(Sale::AUDIENCE_MODE_PUBLIC, $member['audienceMode']);
        self::assertFalse($member['hideProducts']);
        self::assertSame(Sale::OFFSET_TYPE_PERCENTAGE, $member['priceOffsetType']);
        self::assertTrue($member['displayInitialPrice']);
        self::assertSame([$product->getId()], $member['productIds']);
        self::assertSame('Winter operation', $member['i18ns']['en_US']['title'] ?? null);
        self::assertStringStartsWith('SALE-', (string) ($member['i18ns']['en_US']['saleLabel'] ?? ''));
        self::assertStringEndsWith('winter-operation.html', $member['publicUrl']);
    }

    public function testAnInactiveOperationIsNotPartOfTheFrontCollection(): void
    {
        $sale = $this->createFixtureFactory()->sale(['active' => false, 'title' => 'Draft operation']);

        self::assertNull($this->memberOrNull($this->readJson('/api/front/sales'), $sale->getId()));
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/sales/'.$sale->getId())->getStatusCode());
    }

    public function testAReservedOperationIsInvisibleToAVisitor(): void
    {
        $sale = $this->reservedSaleFor($this->newCustomer());

        self::assertNull($this->memberOrNull($this->readJson('/api/front/sales'), $sale->getId()));
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/sales/'.$sale->getId())->getStatusCode());
    }

    public function testAReservedOperationIsInvisibleToACustomerItDoesNotName(): void
    {
        $sale = $this->reservedSaleFor($this->newCustomer());
        $token = $this->authenticateAsCustomer($this->newCustomer('password'));

        self::assertNull($this->memberOrNull($this->readJson('/api/front/sales', $token), $sale->getId()));
        self::assertSame(
            404,
            $this->jsonRequest('GET', '/api/front/sales/'.$sale->getId(), token: $token)->getStatusCode(),
        );
    }

    public function testAReservedOperationIsReadableByTheCustomerItNames(): void
    {
        $customer = $this->newCustomer('password');
        $sale = $this->reservedSaleFor($customer);
        $token = $this->authenticateAsCustomer($customer);

        $member = $this->memberOrNull($this->readJson('/api/front/sales', $token), $sale->getId());

        self::assertNotNull($member);
        self::assertSame(Sale::AUDIENCE_MODE_CUSTOMERS, $member['audienceMode']);
        self::assertSame(
            200,
            $this->jsonRequest('GET', '/api/front/sales/'.$sale->getId(), token: $token)->getStatusCode(),
        );
    }

    public function testTheCountdownFieldsTravelWithTheOperation(): void
    {
        $sale = $this->runningSale([
            'countdownMode' => Sale::COUNTDOWN_MODE_FROM_OPENING,
            // Truncated to the second: MariaDB rounds a fractional DATETIME up,
            // which would leave 7201 seconds on the countdown on an unlucky run.
            'endDate' => new \DateTime((new \DateTime('+2 hours'))->format('Y-m-d H:i:s')),
        ]);

        $member = $this->memberOrNull($this->readJson('/api/front/sales'), $sale->getId());

        self::assertTrue($member['shouldDisplayCountdown']);
        self::assertGreaterThan(7100, $member['countdownRemainingSeconds']);
        self::assertLessThanOrEqual(7200, $member['countdownRemainingSeconds']);
        self::assertSame(Sale::COUNTDOWN_MODE_FROM_OPENING, $member['countdownMode']);
        // A null field is left out of the payload altogether, the way the whole API
        // serializes one: a consumer reads a missing countdownLeadHours as "none".
        self::assertNull($member['countdownLeadHours'] ?? null);
    }

    public function testAnOperationWithNoCountdownAnswersNoRemainingSeconds(): void
    {
        $sale = $this->runningSale(['countdownMode' => Sale::COUNTDOWN_MODE_NONE]);

        $member = $this->memberOrNull($this->readJson('/api/front/sales'), $sale->getId());

        self::assertFalse($member['shouldDisplayCountdown']);
        self::assertNull($member['countdownRemainingSeconds'] ?? null);
    }

    public function testTheCountdownOnlyStartsWithinItsLeadTime(): void
    {
        $tooEarly = $this->runningSale([
            'countdownMode' => Sale::COUNTDOWN_MODE_LEAD_HOURS,
            'countdownLeadHours' => 2,
            'endDate' => new \DateTime('+5 hours'),
            'title' => 'Too early',
        ]);
        $withinReach = $this->runningSale([
            'countdownMode' => Sale::COUNTDOWN_MODE_LEAD_HOURS,
            'countdownLeadHours' => 6,
            'endDate' => new \DateTime('+5 hours'),
            'title' => 'Within reach',
        ]);

        $payload = $this->readJson('/api/front/sales');

        self::assertFalse($this->memberOrNull($payload, $tooEarly->getId())['shouldDisplayCountdown']);
        self::assertNull($this->memberOrNull($payload, $tooEarly->getId())['countdownRemainingSeconds'] ?? null);
        self::assertTrue($this->memberOrNull($payload, $withinReach->getId())['shouldDisplayCountdown']);
        self::assertSame(6, $this->memberOrNull($payload, $withinReach->getId())['countdownLeadHours']);
    }

    /**
     * The guest list of a reserved operation never leaves the back office. This
     * asserts on the shape of the payload rather than on a field name, so a field
     * added later cannot quietly bring it back.
     */
    public function testNoFrontFieldRevealsTheCustomersAnOperationIsReservedFor(): void
    {
        $customer = $this->newCustomer('password');
        $sale = $this->reservedSaleFor($customer);
        $token = $this->authenticateAsCustomer($customer);

        $item = $this->readJson('/api/front/sales/'.$sale->getId(), $token);
        $keys = $this->keysOf($item);
        $payload = json_encode($item, \JSON_THROW_ON_ERROR);

        foreach ($keys as $key) {
            self::assertStringNotContainsStringIgnoringCase(
                'customer',
                $key,
                'No front field of an operation may name who it is reserved for.',
            );
            self::assertStringNotContainsStringIgnoringCase(
                'sale_customer',
                $key,
                'The join table holding the guest list has no business in a front payload.',
            );
        }

        // The whole payload, not only its keys: a value carrying the name of the join
        // table — an IRI, an embedded relation, a serialized column — leaks the same
        // thing a field named after it would.
        self::assertStringNotContainsString('sale_customer', $payload);
        self::assertStringNotContainsStringIgnoringCase('saleCustomer', $payload);

        self::assertStringNotContainsString((string) $customer->getEmail(), $payload);
        self::assertStringNotContainsString('"'.$customer->getId().'"', $payload);
    }

    /**
     * The whole point of the resource: a theme reads one operation and gets the
     * product ids it has to lay out, without asking the catalog first.
     */
    public function testTheOperationHandsOutTheProductsItCovers(): void
    {
        $first = $this->catalogProduct();
        $second = $this->catalogProduct();
        $factory = $this->createFixtureFactory();
        $sale = $this->runningSale([]);
        $factory->saleProduct($sale, $first);
        $factory->saleProduct($sale, $second);

        $item = $this->readJson('/api/front/sales/'.$sale->getId());

        self::assertSame([$first->getId(), $second->getId()], $item['productIds']);
    }

    /**
     * `productIds` is part of every read group, so the serializer asks each member of
     * a page for it. Read one operation at a time that is one sale_product statement
     * per operation, and a shop listing its operations pays for the whole page.
     */
    public function testAPageOfOperationsReadsTheProductsTheyCoverInOneQuery(): void
    {
        $first = $this->catalogProduct();
        $second = $this->catalogProduct();
        $factory = $this->createFixtureFactory();
        $sales = [];

        for ($index = 0; $index < 6; ++$index) {
            $sale = $this->runningSale(['title' => 'Operation '.$index]);
            $factory->saleProduct($sale, $first);
            $factory->saleProduct($sale, $second);
            $sales[] = $sale;
        }

        $payload = [];
        $statements = $this->recordSqlQueries(function () use (&$payload): void {
            $payload = $this->readJson('/api/front/sales');
        });

        // The page still answers what it answered before: the ids, per operation.
        foreach ($sales as $sale) {
            self::assertSame(
                [$first->getId(), $second->getId()],
                $this->memberOrNull($payload, $sale->getId())['productIds'] ?? null,
            );
        }

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'sale_product'),
            'The products of the whole page are read in one statement, not one per operation.',
        );
    }

    private function catalogProduct(): Product
    {
        return $this->createFixtureFactory()->product(
            $this->category,
            $this->taxRule,
            $this->currency,
            ['baseQuantity' => 100, 'basePrice' => 100.0],
        );
    }

    private function runningSale(array $overrides): Sale
    {
        return $this->createFixtureFactory()->sale($overrides + [
            'active' => true,
            'title' => 'Winter operation',
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
        ]);
    }

    private function reservedSaleFor(Customer $customer): Sale
    {
        $sale = $this->runningSale([
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'title' => 'Reserved operation',
        ]);
        $this->createFixtureFactory()->saleCustomer($sale, $customer);

        return $sale;
    }

    private function newCustomer(?string $password = null): Customer
    {
        $factory = $this->createFixtureFactory();

        return $factory->customer(
            $factory->customerTitle(),
            null === $password ? [] : ['password' => $password],
        );
    }

    /**
     * @param array<mixed> $payload
     *
     * @return list<string>
     */
    private function keysOf(array $payload): array
    {
        $keys = [];

        foreach ($payload as $key => $value) {
            if (\is_string($key)) {
                $keys[] = $key;
            }

            if (\is_array($value)) {
                $keys = [...$keys, ...$this->keysOf($value)];
            }
        }

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $uri, ?string $token = null): array
    {
        $response = $this->jsonRequest('GET', $uri, token: $token);
        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    private function memberOrNull(array $payload, ?int $saleId): ?array
    {
        foreach ($payload['hydra:member'] ?? [] as $member) {
            if (($member['id'] ?? null) === $saleId) {
                return $member;
            }
        }

        return null;
    }
}
