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

namespace Thelia\Tests\Integration\Company;

use Symfony\Component\Mailer\MailerInterface;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Tests\Support\Order\RecordsStatements;

/**
 * The company that wants to follow what its buyers order gets a copy of every e-mail the
 * customer receives about one of its orders, and nobody else gets anything more.
 */
final class OrderCompanyCopyTest extends OrderCompanyTestCase
{
    use RecordsStatements;

    private const string COPY = 'purchasing@acme.example';

    private OrderCopyMailerFactory $mailer;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('store_email', 'shop@shop.example');
        ConfigQuery::write('store_notification_emails', 'manager@shop.example');
        ConfigQuery::resetCache();

        $this->mailer = new OrderCopyMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
            self::getContainer()->get('mailer.transports'),
        );
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheCustomerMessageAboutAnOrderOfTheCompanyIsCopiedToItsAddress(): void
    {
        [$buyer, $order] = $this->orderOfACompany(self::COPY);

        $this->mailer->sendEmailToCustomerOrFail('order_confirmation', $buyer, ['order_id' => $order->getId(), 'order_ref' => $order->getRef()]);

        self::assertCount(1, $this->mailer->sent);
        self::assertSame([self::COPY], $this->mailer->copiesOf(0));
        self::assertSame([$buyer->getEmail()], $this->mailer->recipientsOf(0), 'The customer is still the recipient.');
    }

    public function testTheMessageIsFoundByTheReferenceOfTheOrderWhenOnlyThatIsGiven(): void
    {
        [$buyer, $order] = $this->orderOfACompany(self::COPY);

        $this->mailer->sendEmailToCustomerOrFail('order_shipped', $buyer, ['order_ref' => $order->getRef()]);

        self::assertSame([self::COPY], $this->mailer->copiesOf(0));
    }

    public function testNoCopyIsSentWhenTheCompanyHasNoAddress(): void
    {
        [$buyer, $order] = $this->orderOfACompany(null);
        [$other, $otherOrder] = $this->orderOfACompany('   ');

        $this->mailer->sendEmailToCustomerOrFail('order_confirmation', $buyer, ['order_id' => $order->getId()]);
        $this->mailer->sendEmailToCustomerOrFail('order_confirmation', $other, ['order_id' => $otherOrder->getId()]);

        self::assertSame([[], []], [$this->mailer->copiesOf(0), $this->mailer->copiesOf(1)]);
    }

    public function testNoCopyIsSentForTheOrderOfACustomerWithoutACompany(): void
    {
        $customer = $this->customer();
        $order = $this->place($this->cartOf($customer, '100'), $customer);

        $statements = $this->statementsDuring(fn () => $this->mailer->sendEmailToCustomerOrFail('order_confirmation', $customer, ['order_id' => $order->getId()]));

        self::assertSame([], $this->mailer->copiesOf(0));
        self::assertSame([], array_values(array_filter($statements, static fn (string $statement): bool => 1 === preg_match('/\b(FROM|JOIN)\s+`(company|customer)`/i', $statement))), 'An order with no company costs the read of the order, and nothing else.');
    }

    public function testAMessageAboutNoOrderIsNotCopiedAndReadsNothing(): void
    {
        [$buyer] = $this->orderOfACompany(self::COPY);

        $statements = $this->statementsDuring(fn () => $this->mailer->sendEmailToCustomerOrFail('customer_account_changed', $buyer));

        self::assertSame([], $this->mailer->copiesOf(0));
        self::assertSame([], array_values(array_filter($statements, static fn (string $statement): bool => str_contains($statement, '`order`') || str_contains($statement, '`company`'))));
    }

    public function testTheShopManagersAreNotTheCustomerAndGetNoCopy(): void
    {
        [, $order] = $this->orderOfACompany(self::COPY);

        $this->mailer->sendEmailToShopManagersOrFail('order_notification', ['order_id' => $order->getId()]);

        self::assertCount(1, $this->mailer->sent);
        self::assertSame(['manager@shop.example'], $this->mailer->recipientsOf(0));
        self::assertSame([], $this->mailer->copiesOf(0));
    }

    public function testAMessageSentDirectlyToTheCustomerOfTheOrderIsCopiedToo(): void
    {
        [$buyer, $order] = $this->orderOfACompany(self::COPY);

        // What the modules that write their own recipients do: the carrier's shipping notice.
        $this->mailer->sendEmailMessageOrFail('order_shipped', ['shop@shop.example' => 'Shop'], [$buyer->getEmail() => 'Buyer'], ['order_id' => $order->getId()], 'en_US', ['audit@shop.example' => 'Audit']);

        self::assertEqualsCanonicalizing(['audit@shop.example', self::COPY], $this->mailer->copiesOf(0), 'The copies the caller asked for are kept.');
    }

    public function testAMessageToSomeoneElseAboutTheOrderIsNotCopied(): void
    {
        [, $order] = $this->orderOfACompany(self::COPY);

        $this->mailer->sendEmailMessageOrFail('order_shipped', ['shop@shop.example' => 'Shop'], ['somebody@else.example' => 'Somebody'], ['order_id' => $order->getId()]);

        self::assertSame([], $this->mailer->copiesOf(0));
    }

    public function testTheAddressIsNotRepeatedWhenItIsTheOneOfTheCustomer(): void
    {
        $company = $this->company();
        $company->setNotificationEmail('Buyer@Acme.example')->save($this->getPropelConnection());
        $buyer = $this->buyerOf($company, customer: $this->customer('buyer@acme.example'));
        $order = $this->place($this->cartOf($buyer, '100'), $buyer);

        $this->mailer->sendEmailToCustomerOrFail('order_confirmation', $buyer, ['order_id' => $order->getId()]);

        self::assertSame([], $this->mailer->copiesOf(0));
    }

    /**
     * @return array{0: Customer, 1: Order}
     */
    private function orderOfACompany(?string $notificationEmail): array
    {
        $company = $this->company();
        $company->setNotificationEmail($notificationEmail)->save($this->getPropelConnection());
        $buyer = $this->buyerOf($company);

        return [$buyer, $this->place($this->cartOf($buyer, '100'), $buyer)];
    }
}
