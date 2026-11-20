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

namespace Thelia\Tests\Http\Flexy;

use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A customer who belongs to no company: the pages of the account and the checkout are the ones they
 * always were, with nothing of the company in them, and the shop asks the company tables one
 * indexed question, once.
 */
final class CompanyAccountRegressionTest extends CompanyAccountTestCase
{
    use RecordsSqlQueries;

    private const PAGES = ['/account', '/account/addresses', '/account/orders', '/account/address/new', '/checkout/cart'];

    public function testNoPageOfACustomerWithoutCompanyMentionsOne(): void
    {
        $customer = $this->customer();
        $this->sharedAddress($customer, null, 'Cellar');
        $this->signInAs($customer);

        foreach (self::PAGES as $path) {
            $page = $this->get($path);

            self::assertSame(200, $this->statusCode(), $path);
            $text = $page->filter('body')->text();

            foreach (['My company', 'Orders of the company', 'Shared with the company', 'Share this address', 'company account is suspended'] as $sentence) {
                self::assertStringNotContainsString($sentence, $text, $path);
            }

            self::assertCount(0, $page->filter('[data-testid^="company"], [data-testid="address-shared"], [data-testid="order-placed-by"]'), $path);
            self::assertCount(0, $page->filter('a[href^="/account/company"]'), $path);
        }
    }

    public function testTheCompanyTablesAreAskedOneQuestionOnTheAccountPages(): void
    {
        $customer = $this->customer();
        $this->sharedAddress($customer, null, 'Cellar');
        $this->signInAs($customer);

        foreach (['/account/addresses', '/account/orders', '/account/address/new'] as $path) {
            $statements = $this->recordSqlQueries(fn () => $this->get($path));
            $companyReads = array_values(array_filter($statements, static fn (string $sql): bool => 1 === preg_match('/\b(FROM|JOIN)\s+`?company(_customer|_role)?`?\b/i', $sql)));

            self::assertCount(1, $companyReads, $path.': '.implode(' | ', array_map(static fn (string $sql): string => substr($sql, 0, 120), $companyReads)));
            self::assertStringContainsString('company_customer', $companyReads[0], $path);
        }
    }
}
