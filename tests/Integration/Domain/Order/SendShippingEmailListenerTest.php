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

namespace Thelia\Tests\Integration\Domain\Order;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Mailer\MailerInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Invoice\InvoiceRefAllocator;
use Thelia\Domain\Order\EventListener\SendShippingEmailListener;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Domain\Order\Service\OrderStatusCatalog;
use Thelia\Domain\Order\Service\OrderTrackingUrlResolver;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\MessageQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Module\BaseModule;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\RecordingMailerFactory;
use Thelia\Tests\Support\Order\MovesOrders;

/**
 * The e-mail that tells the customer their order has left.
 *
 * Asserted on the parameters handed to the message rather than on the rendered text:
 * what the core owes is the order, the carrier, the number and a usable link; the
 * wording belongs to the e-mail template, which a shop is free to replace.
 */
final class SendShippingEmailListenerTest extends ActionIntegrationTestCase
{
    use MovesOrders;

    private const string CARRIER_CODE = 'ShippingEmailTestCarrier';

    private RecordingMailerFactory $mailer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailer = new RecordingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
            $this->getService(OrderHistoryRecorder::class),
        );
        // Kept out of the way: what is asserted is the shipping e-mail alone.
        ConfigQuery::write(InvoiceRefAllocator::CONFIG_ENABLED, '0');
    }

    protected function tearDown(): void
    {
        // The rollback puts the rows back, never the static caches filled by the writes.
        ConfigQuery::resetCache();
        ModuleConfigQuery::resetConfigCache();

        parent::tearDown();
    }

    public function testEnteringTheSentStatusMailsTheCarrierTheNumberAndTheLink(): void
    {
        $order = $this->processingOrderShippedBy($this->carrier('https://carrier.example/track?parcel=%ID%'), '6A 12');

        $this->listener()->onOrderStatusUpdate($this->statusChange($order, OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT));

        $sent = $this->mailer->parametersOfMessagesSent(SendShippingEmailListener::MESSAGE_CODE);
        self::assertCount(1, $sent);
        self::assertSame($order->getId(), $sent[0]['order_id']);
        self::assertSame($order->getRef(), $sent[0]['order_ref']);
        self::assertSame('6A 12', $sent[0]['delivery_ref']);
        self::assertSame('https://carrier.example/track?parcel=6A%2012', $sent[0]['tracking_url']);
        self::assertSame('Carrier for the shipping e-mail', $sent[0]['carrier']);
        self::assertSame($order->getCustomerId(), $sent[0]['customer_id']);
        self::assertSame($order->getCustomer()->getEmail(), array_key_first($this->mailer->messages[0]['to']));
    }

    /**
     * The carrier is named in the language of the customer, and naming it leaves the
     * shared module instance in the language it had: a back office page rendered
     * after a bulk move keeps its own language.
     */
    public function testTheCarrierIsNamedInTheLanguageOfTheCustomerWithoutSwitchingTheModule(): void
    {
        $carrier = $this->carrier(null);
        $carrier->setLocale('fr_FR')->setTitle('Transporteur de test')->save($this->getPropelConnection());
        $carrier->setLocale('en_US');
        $order = $this->processingOrderShippedBy($carrier, '6A12');
        $order->getCustomer()->setLangId(LangQuery::create()->findOneByLocale('fr_FR')->getId())->save($this->getPropelConnection());
        $module = $order->getModuleRelatedByDeliveryModuleId();
        $module->setLocale('en_US');

        $this->listener()->onOrderStatusUpdate($this->statusChange($order, OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT));

        self::assertSame('Transporteur de test', $this->mailer->parametersOfMessagesSent(SendShippingEmailListener::MESSAGE_CODE)[0]['carrier']);
        self::assertSame('Carrier for the shipping e-mail', $module->getTitle(), 'The module keeps the language it was read in.');
    }

    public function testWithoutTrackingNumberTheEmailStillAnnouncesTheShipment(): void
    {
        $order = $this->processingOrderShippedBy($this->carrier('https://carrier.example/track?parcel=%ID%'), null);

        $this->listener()->onOrderStatusUpdate($this->statusChange($order, OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT));

        $sent = $this->mailer->parametersOfMessagesSent(SendShippingEmailListener::MESSAGE_CODE);
        self::assertCount(1, $sent);
        self::assertNull($sent[0]['delivery_ref']);
        self::assertNull($sent[0]['tracking_url']);
    }

    public function testACarrierWithoutTrackingAddressMailsTheNumberWithoutLink(): void
    {
        $order = $this->processingOrderShippedBy($this->carrier(null), '6A12');

        $this->listener()->onOrderStatusUpdate($this->statusChange($order, OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT));

        $sent = $this->mailer->parametersOfMessagesSent(SendShippingEmailListener::MESSAGE_CODE);
        self::assertSame('6A12', $sent[0]['delivery_ref']);
        self::assertNull($sent[0]['tracking_url']);
    }

    public function testAnOrderWhoseCarrierWasUninstalledIsMailedWithTheCarrierNameItKept(): void
    {
        $order = $this->processingOrderShippedBy($this->carrier('https://carrier.example/%ID%'), '6A12');
        $order->setDeliveryModuleId(null)->setDeliveryModuleTitle('Former carrier')->save($this->getPropelConnection());

        $this->listener()->onOrderStatusUpdate($this->statusChange($order, OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT));

        $sent = $this->mailer->parametersOfMessagesSent(SendShippingEmailListener::MESSAGE_CODE);
        self::assertSame('Former carrier', $sent[0]['carrier']);
        self::assertNull($sent[0]['tracking_url']);
    }

    public function testAnOrderSavedAgainInTheSentStatusIsNotMailedTwice(): void
    {
        $order = $this->processingOrderShippedBy($this->carrier(null), '6A12');

        $this->listener()->onOrderStatusUpdate($this->statusChange($order, OrderStatus::CODE_SENT, OrderStatus::CODE_SENT));

        self::assertSame([], $this->mailer->parametersOfMessagesSent(SendShippingEmailListener::MESSAGE_CODE));
    }

    public function testLeavingTheSentStatusMailsNothing(): void
    {
        $order = $this->processingOrderShippedBy($this->carrier(null), '6A12');

        $this->listener()->onOrderStatusUpdate($this->statusChange($order, OrderStatus::CODE_SENT, OrderStatus::CODE_CANCELED));

        self::assertSame([], $this->mailer->parametersOfMessagesSent(SendShippingEmailListener::MESSAGE_CODE));
    }

    public function testACustomStatusEquivalentToSentCountsAsShipping(): void
    {
        $handedToCarrier = $this->factory->orderStatus(['code' => 'handed_to_carrier', 'equivalentCode' => OrderStatus::CODE_SENT]);
        $order = $this->processingOrderShippedBy($this->carrier(null), '6A12');

        $event = new OrderEvent($order);
        $event->setPreviousStatusId($this->orderStatus(OrderStatus::CODE_PROCESSING)->getId());
        $event->setStatus($handedToCarrier->getId());
        $this->listener()->onOrderStatusUpdate($event);

        self::assertCount(1, $this->mailer->parametersOfMessagesSent(SendShippingEmailListener::MESSAGE_CODE));
    }

    public function testMovingBetweenTwoStatusesThatBothMeanSentMailsNothing(): void
    {
        $handedToCarrier = $this->factory->orderStatus(['code' => 'handed_to_carrier', 'equivalentCode' => OrderStatus::CODE_SENT]);
        $order = $this->processingOrderShippedBy($this->carrier(null), '6A12');

        $event = new OrderEvent($order);
        $event->setPreviousStatusId($this->orderStatus(OrderStatus::CODE_SENT)->getId());
        $event->setStatus($handedToCarrier->getId());
        $this->listener()->onOrderStatusUpdate($event);

        self::assertSame([], $this->mailer->parametersOfMessagesSent(SendShippingEmailListener::MESSAGE_CODE));
    }

    public function testASwitchedOffEmailIsNotSent(): void
    {
        ConfigQuery::write(SendShippingEmailListener::ENABLED_CONFIG_KEY, '0');
        $order = $this->processingOrderShippedBy($this->carrier(null), '6A12');

        $this->listener()->onOrderStatusUpdate($this->statusChange($order, OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT));

        self::assertSame([], $this->mailer->parametersOfMessagesSent(SendShippingEmailListener::MESSAGE_CODE));
    }

    public function testAShopWithoutTheMessageChangesTheStatusAndMailsNothing(): void
    {
        MessageQuery::create()->filterByName(SendShippingEmailListener::MESSAGE_CODE)->delete($this->getPropelConnection());
        $order = $this->processingOrderShippedBy($this->carrier(null), '6A12');

        $this->listener()->onOrderStatusUpdate($this->statusChange($order, OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT));

        self::assertSame([], $this->mailer->messages);
    }

    /**
     * The real chain, as the back office, a payment module or the API drive it: the
     * status event is dispatched with no request and no session around it, and an
     * order saved again as sent afterwards is not mailed a second time.
     */
    public function testTheStatusEventMailsTheCustomerOnceWithoutAnyRequest(): void
    {
        static::getContainer()->set(MailerFactory::class, $this->mailer);
        $order = $this->processingOrderShippedBy($this->carrier(null), '6A12');

        $this->moveOrderTo($order, OrderStatus::CODE_SENT);
        $this->moveOrderTo(OrderQuery::create()->findPk($order->getId()), OrderStatus::CODE_SENT);

        self::assertCount(1, $this->mailer->parametersOfMessagesSent(SendShippingEmailListener::MESSAGE_CODE));
        self::assertTrue(OrderQuery::create()->findPk($order->getId())->isSent());
    }

    public function testCorrectingTheNumberOfAShippedOrderMailsNothingAndMovesTheLink(): void
    {
        static::getContainer()->set(MailerFactory::class, $this->mailer);
        $order = $this->processingOrderShippedBy($this->carrier('https://carrier.example/%ID%'), '6A12');
        $this->moveOrderTo($order, OrderStatus::CODE_SENT);

        $event = new OrderEvent(OrderQuery::create()->findPk($order->getId()));
        $event->setDeliveryRef('6A99');
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_DELIVERY_REF);

        self::assertCount(1, $this->mailer->parametersOfMessagesSent(SendShippingEmailListener::MESSAGE_CODE), 'Only the shipment itself was mailed.');
        self::assertSame(
            'https://carrier.example/6A99',
            $this->resolver()->resolve(OrderQuery::create()->findPk($order->getId())),
        );
    }

    private function listener(): SendShippingEmailListener
    {
        return new SendShippingEmailListener($this->mailer, new OrderStatusCatalog(), $this->resolver());
    }

    private function resolver(): OrderTrackingUrlResolver
    {
        return new OrderTrackingUrlResolver(new Container());
    }

    private function statusChange(Order $order, string $fromCode, string $toCode): OrderEvent
    {
        $event = new OrderEvent($order);
        $event->setPreviousStatusId($this->orderStatus($fromCode)->getId());
        $event->setStatus($this->orderStatus($toCode)->getId());

        return $event;
    }

    private function carrier(?string $trackingUrlTemplate): Module
    {
        $module = new Module();
        $module
            ->setCode(self::CARRIER_CODE)
            ->setType(BaseModule::DELIVERY_MODULE_TYPE)
            ->setActivate(BaseModule::IS_ACTIVATED)
            ->setFullNamespace(self::CARRIER_CODE.'\\'.self::CARRIER_CODE)
            ->setLocale('en_US')
            ->setTitle('Carrier for the shipping e-mail')
            ->save($this->getPropelConnection());

        if (null !== $trackingUrlTemplate) {
            ModuleConfigQuery::create()->setConfigValue($module->getId(), OrderTrackingUrlResolver::TRACKING_URL_CONFIG_KEY, $trackingUrlTemplate);
        }

        return $module;
    }

    private function processingOrderShippedBy(Module $carrier, ?string $trackingNumber): Order
    {
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PROCESSING, 'deliveryModuleCode' => $carrier->getCode()]);
        $order->setDeliveryRef($trackingNumber)->save($this->getPropelConnection());

        return $order;
    }
}
