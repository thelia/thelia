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

use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\Company;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;

/**
 * The orders of a company in the account, for the two companies and the three kinds of buyer the
 * visibility rule names: the buyer who placed the order, the buyer whose role reads the orders of
 * the company, and everyone else, a colleague without that role and another company included.
 */
final class CompanyOrdersTest extends CompanyAccountTestCase
{
    private Company $first;

    private Customer $placer;

    private Customer $colleague;

    private Customer $approver;

    private Customer $administrator;

    private Customer $administratorOfSecond;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->first = $this->company('Acme');
        $second = $this->company('Globex');
        $this->placer = $this->buyer($this->first);
        $this->colleague = $this->buyer($this->first);
        $this->approver = $this->buyer($this->first, CompanyRoleCode::APPROVER);
        $this->administrator = $this->buyer($this->first, CompanyRoleCode::ADMINISTRATOR);
        $this->administratorOfSecond = $this->buyer($second, CompanyRoleCode::ADMINISTRATOR);

        $this->order = $this->factory->order($this->placer, ['statusCode' => OrderStatus::CODE_PAID]);
        $this->order->setCompanyId($this->first->getId())->setPlacedByCustomerId($this->placer->getId())->save($this->getPropelConnection());
    }

    public function testTheOrderPageAndTheDocumentsAreForTheBuyerWhoPlacedItAndTheBuyersWhoReadTheCompany(): void
    {
        foreach ($this->pagesOfTheOrder() as $path) {
            foreach ([$this->placer, $this->approver, $this->administrator] as $who) {
                $this->signInAs($who);
                $this->get($path);

                self::assertNotSame(404, $this->statusCode(), $who->getEmail().' on '.$path);
            }

            foreach ([$this->colleague, $this->administratorOfSecond] as $who) {
                $this->signInAs($who);
                $this->get($path);

                self::assertSame(404, $this->statusCode(), $who->getEmail().' on '.$path);
            }
        }
    }

    public function testACustomerWithoutCompanyStillReadsOnlyTheirOwnOrder(): void
    {
        $this->signInAs($this->customer());

        foreach ($this->pagesOfTheOrder() as $path) {
            $this->get($path);

            self::assertSame(404, $this->statusCode(), $path);
        }
    }

    public function testTheCompanyTabListsTheOrdersOfTheCompanyWithTheirBuyerForWhoReadsThem(): void
    {
        foreach ([$this->approver, $this->administrator] as $who) {
            $this->signInAs($who);

            $page = $this->get('/account/orders?scope=company');

            self::assertSame(200, $this->statusCode());
            self::assertStringContainsString((string) $this->order->getRef(), $page->filter('body')->text(), $who->getEmail());
            self::assertStringContainsString($this->placer->getFirstname(), $page->filter('[data-testid="order-placed-by"]')->text());
            self::assertCount(2, $page->filter('nav[aria-label="My orders"] a'), 'The two lists have a tab.');
        }
    }

    public function testTheCompanyTabIsRefusedToWhoLacksThePermissionAndTheirOwnListHasNoTab(): void
    {
        foreach ([$this->colleague, $this->placer] as $who) {
            $this->signInAs($who);

            $this->get('/account/orders?scope=company');
            self::assertSame(403, $this->statusCode(), $who->getEmail());

            $page = $this->get('/account/orders');
            self::assertSame(200, $this->statusCode());
            self::assertCount(0, $page->filter('nav[aria-label="My orders"]'), 'No tab for a role that cannot read the company.');
        }
    }

    public function testAColleagueWithoutThePermissionDoesNotSeeTheOrderInTheirList(): void
    {
        $this->signInAs($this->colleague);
        self::assertStringNotContainsString((string) $this->order->getRef(), $this->get('/account/orders')->filter('body')->text());

        $this->signInAs($this->placer);
        self::assertStringContainsString((string) $this->order->getRef(), $this->get('/account/orders')->filter('body')->text());
    }

    public function testAnotherCompanyNeverSeesTheOrderInAnyList(): void
    {
        $this->signInAs($this->administratorOfSecond);

        foreach (['/account/orders', '/account/orders?scope=company'] as $path) {
            self::assertStringNotContainsString((string) $this->order->getRef(), $this->get($path)->filter('body')->text(), $path);
        }
    }

    public function testTheListOfTheOwnOrdersOfAnAdministratorLeavesOutTheOrdersOfTheirBuyers(): void
    {
        $this->signInAs($this->administrator);

        $page = $this->get('/account/orders?scope=own');

        self::assertStringNotContainsString((string) $this->order->getRef(), $page->filter('body')->text());
    }

    /**
     * @return list<string>
     */
    private function pagesOfTheOrder(): array
    {
        $id = $this->order->getId();

        return [
            '/account/order/'.$id,
            '/account/order/pdf/invoice/'.$id,
            '/account/order/pdf/delivery/'.$id,
        ];
    }
}
