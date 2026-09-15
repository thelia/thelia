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

namespace Thelia\Tests\Integration\Mailer;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderHistory;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * A mail the shop sends about an order belongs in the history of that order: a
 * customer who says they were never told is answered with a date and a message
 * code, and nobody has to go looking through a mail server log.
 *
 * Nothing of the mail itself is kept — not the body, not the subject, not the
 * address it went to. Which order a message is about is not in the signature of
 * any send: it is read off the `order_id` and `order_ref` parameters every caller
 * in the core and in the shipped modules already passes to the template.
 */
final class OrderEmailHistoryTest extends IntegrationTestCase
{
    private const MESSAGE_CODE = 'order_confirmation';

    private const REFERENCE_ONLY_MESSAGE_CODE = 'order_confirmation_cheque';

    private MailerFactory $mailerFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailerFactory = $this->getService(MailerFactory::class);

        // A shop with no sender address sends nothing at all, and the test database is
        // installed without one.
        ConfigQuery::write('store_email', 'shop@example.com');
    }

    protected function tearDown(): void
    {
        // The row goes back with the wrapper transaction; the ConfigQuery static cache
        // does not.
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testAMailAboutAnOrderIsAddedToItsHistory(): void
    {
        $order = $this->createFixtureFactory()->order();

        $this->sendAboutOrder(['order_id' => $order->getId(), 'order_ref' => $order->getRef()]);

        $entry = $this->emailEntry($order);

        self::assertNotNull($entry, 'A mail sent about an order must leave a line in its history.');
        self::assertSame(['message_code' => self::MESSAGE_CODE], $entry->getDecodedPayload());
    }

    /**
     * A caller that names the order by its reference only still gets the mail attached
     * to the right order. The message is one whose template asks for nothing else, so
     * that what is under test is the lookup and not the wording of a template.
     */
    public function testTheOrderReferenceAloneIsEnoughToFindTheOrder(): void
    {
        $order = $this->createFixtureFactory()->order();
        $order->setRef('ORD-HISTORY-REF')->save($this->getPropelConnection());

        $this->sendAboutOrder(['order_ref' => $order->getRef()], self::REFERENCE_ONLY_MESSAGE_CODE);

        $entry = OrderHistoryQuery::create()
            ->findLatestOfType($order->getId(), OrderHistoryEventType::EMAIL_SENT->value);

        self::assertNotNull($entry);
        self::assertSame(['message_code' => self::REFERENCE_ONLY_MESSAGE_CODE], $entry->getDecodedPayload());
    }

    public function testTheMailBodyAndRecipientNeverReachTheHistory(): void
    {
        $order = $this->createFixtureFactory()->order();

        $this->sendAboutOrder(['order_id' => $order->getId(), 'order_ref' => $order->getRef()]);

        $entry = $this->emailEntry($order);
        self::assertNotNull($entry);

        self::assertSame(['message_code' => self::MESSAGE_CODE], $entry->getDecodedPayload());
        self::assertNull($entry->getComment());
    }

    /**
     * A password reset, a newsletter confirmation, a contact form answer: most of what
     * a shop sends is about nobody's order, and none of it belongs in this table.
     */
    public function testAMailAboutNoOrderRecordsNothing(): void
    {
        // A shop has orders on file; none of them is the subject of this mail.
        $bystander = $this->createFixtureFactory()->order();
        $emailEntriesBefore = $this->emailEntryCount();

        $this->sendAboutOrder(['customer_id' => 1], self::REFERENCE_ONLY_MESSAGE_CODE);

        self::assertSame($emailEntriesBefore, $this->emailEntryCount());
        self::assertNull($this->emailEntry($bystander));
    }

    public function testAReferenceNoOrderCarriesRecordsNothing(): void
    {
        $bystander = $this->createFixtureFactory()->order();
        $emailEntriesBefore = $this->emailEntryCount();

        $this->sendAboutOrder(['order_ref' => 'ORD-THAT-DOES-NOT-EXIST'], self::REFERENCE_ONLY_MESSAGE_CODE);

        self::assertSame($emailEntriesBefore, $this->emailEntryCount());
        self::assertNull($this->emailEntry($bystander));
    }

    /**
     * A history saying the buyer was written to when nothing left the shop is worse
     * than one saying nothing: the line is written after the send, never before. The
     * message below is built correctly and the transport refuses it, which is what an
     * unreachable mail server looks like from here.
     */
    public function testAMailThatCouldNotBeSentRecordsNothing(): void
    {
        $order = $this->createFixtureFactory()->order();

        $refusingTransport = $this->createMock(MailerInterface::class);
        $refusingTransport
            ->method('send')
            ->willThrowException(new TransportException('The mail server is unreachable.'));

        $factoryWithARefusingTransport = new MailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $refusingTransport,
            $this->getService(OrderHistoryRecorder::class),
        );

        $factoryWithARefusingTransport->sendEmailMessage(
            self::MESSAGE_CODE,
            [ConfigQuery::getStoreEmail() => ConfigQuery::getStoreName()],
            ['buyer@example.com' => 'Buyer'],
            ['order_id' => $order->getId(), 'order_ref' => $order->getRef()],
            'en_US',
        );

        self::assertNull($this->emailEntry($order));
    }

    private function emailEntryCount(): int
    {
        return OrderHistoryQuery::create()
            ->filterByEventType(OrderHistoryEventType::EMAIL_SENT->value)
            ->count();
    }

    /**
     * @param array<string, mixed> $messageParameters
     */
    private function sendAboutOrder(array $messageParameters, string $messageCode = self::MESSAGE_CODE): void
    {
        $this->mailerFactory->sendEmailMessage(
            $messageCode,
            [ConfigQuery::getStoreEmail() => ConfigQuery::getStoreName()],
            ['buyer@example.com' => 'Buyer'],
            $messageParameters,
            'en_US',
        );
    }

    private function emailEntry(Order $order): ?OrderHistory
    {
        return OrderHistoryQuery::create()
            ->findLatestOfType($order->getId(), OrderHistoryEventType::EMAIL_SENT->value);
    }
}
