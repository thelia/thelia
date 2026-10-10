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

namespace Thelia\Tests\Integration\Module;

use CustomDelivery\CustomDelivery;
use CustomDelivery\EventListeners\CustomDeliveryEvents;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\MailerInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Order\EventListener\SendShippingEmailListener;
use Thelia\Domain\Order\Service\OrderTrackingUrlResolver;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\RecordingMailerFactory;

/**
 * CustomDelivery used to send its own shipping message. Next to the shipping e-mail of
 * the core, a shop must not mail its customers twice, and the tracking address typed in
 * the module must keep giving the link once the module is updated.
 *
 * The module ships as its own package: a version older than this coexistence is
 * reported as skipped rather than failed.
 */
final class CustomDeliveryShippingEmailTest extends IntegrationTestCase
{
    private RecordingMailerFactory $mailer;

    protected function setUp(): void
    {
        if (!method_exists(CustomDelivery::class, 'isValidTrackingUrlTemplateWithoutCore')) {
            self::markTestSkipped('The installed CustomDelivery predates the shipping e-mail of the core.');
        }

        parent::setUp();

        $this->mailer = new RecordingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
        );
        ConfigQuery::write('store_email', 'shop@example.com');
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        ModuleConfigQuery::resetConfigCache();

        parent::tearDown();
    }

    public function testTheModuleStaysQuietWhileTheCoreSendsTheShippingEmail(): void
    {
        $this->listener()->updateStatus($this->shipped($this->customDeliveryOrder('6A12')));

        self::assertSame([], $this->mailer->parametersOfMessagesSent('mail_custom_delivery'));
    }

    /**
     * The core owns the shipping e-mail: a merchant who switched it off in the store
     * configuration does not get the module's message instead.
     */
    public function testTheModuleStaysQuietWhenTheCoreEmailIsSwitchedOff(): void
    {
        ConfigQuery::write(SendShippingEmailListener::ENABLED_CONFIG_KEY, '0');

        $this->listener()->updateStatus($this->shipped($this->customDeliveryOrder('6A12')));

        self::assertSame([], $this->mailer->parametersOfMessagesSent('mail_custom_delivery'));
    }

    public function testTheTransitionSwitchKeepsTheModuleMessageWithAnEncodedLink(): void
    {
        CustomDelivery::setConfigValue(CustomDelivery::CONFIG_SEND_OWN_SHIPPING_EMAIL, '1');
        CustomDelivery::saveTrackingUrlTemplate('https://carrier.example/track?parcel=%ID%');

        $this->listener()->updateStatus($this->shipped($this->customDeliveryOrder('6A 12')));

        $sent = $this->mailer->parametersOfMessagesSent('mail_custom_delivery');
        self::assertCount(1, $sent);
        self::assertSame('https://carrier.example/track?parcel=6A%2012', $sent[0]['tracking_url']);
    }

    public function testAnOrderSavedAgainAsSentIsNotMailedTwiceByTheModule(): void
    {
        CustomDelivery::setConfigValue(CustomDelivery::CONFIG_SEND_OWN_SHIPPING_EMAIL, '1');
        $order = $this->customDeliveryOrder('6A12');

        $event = new OrderEvent($order);
        $event->setPreviousStatusId($this->orderStatus(OrderStatus::CODE_SENT)->getId());
        $event->setStatus($this->orderStatus(OrderStatus::CODE_SENT)->getId());
        $this->listener()->updateStatus($event);

        self::assertSame([], $this->mailer->parametersOfMessagesSent('mail_custom_delivery'));
    }

    /**
     * A shop that updates the module keeps its tracking address without typing it again:
     * the update copies it where the core reads it.
     */
    public function testTheUpdateMovesTheTrackingAddressWhereTheCoreReadsIt(): void
    {
        ConfigQuery::write(CustomDelivery::CONFIG_TRACKING_URL, 'https://carrier.example/%ID%');
        ModuleConfigQuery::create()->deleteConfigValue(CustomDelivery::getModuleId(), CustomDelivery::CORE_TRACKING_URL_KEY);

        (new CustomDelivery())->update('4.0.4', '4.1.0', $this->getPropelConnection());
        ModuleConfigQuery::resetConfigCache();

        self::assertSame(
            'https://carrier.example/6A12',
            $this->getService(OrderTrackingUrlResolver::class)->resolve($this->customDeliveryOrder('6A12')),
        );
    }

    /**
     * Cleared from the shipping page of the back office, the address stays cleared: the
     * former global setting is not copied back by a later update or reactivation.
     */
    public function testAClearedTrackingAddressDoesNotComeBack(): void
    {
        ConfigQuery::write(CustomDelivery::CONFIG_TRACKING_URL, 'https://carrier.example/%ID%');
        ModuleConfigQuery::create()->deleteConfigValue(CustomDelivery::getModuleId(), CustomDelivery::CORE_TRACKING_URL_KEY);
        (new CustomDelivery())->update('4.0.4', '4.1.0', $this->getPropelConnection());

        ModuleConfigQuery::create()->deleteConfigValue(CustomDelivery::getModuleId(), CustomDelivery::CORE_TRACKING_URL_KEY);
        (new CustomDelivery())->update('4.0.4', '4.1.0', $this->getPropelConnection());
        ModuleConfigQuery::resetConfigCache();

        self::assertSame('', CustomDelivery::getTrackingUrlTemplate());
        self::assertSame(CustomDelivery::DEFAULT_TRACKING_URL, ConfigQuery::read(CustomDelivery::CONFIG_TRACKING_URL));
    }

    public function testTheOldDefaultIsNoTrackingAddress(): void
    {
        ConfigQuery::write(CustomDelivery::CONFIG_TRACKING_URL, CustomDelivery::DEFAULT_TRACKING_URL);
        ModuleConfigQuery::create()->deleteConfigValue(CustomDelivery::getModuleId(), CustomDelivery::CORE_TRACKING_URL_KEY);

        (new CustomDelivery())->update('4.0.4', '4.1.0', $this->getPropelConnection());
        ModuleConfigQuery::resetConfigCache();

        self::assertSame('', CustomDelivery::getTrackingUrlTemplate());
        self::assertNull($this->getService(OrderTrackingUrlResolver::class)->resolve($this->customDeliveryOrder('6A12')));
    }

    /**
     * On a core without the rule the module checks the address itself: both rules must
     * accept and refuse the same addresses, or a template valid in the module would be
     * dropped by the core, and the other way round.
     */
    #[DataProvider('templates')]
    public function testTheModuleRuleForAnOlderCoreMatchesTheCoreRule(string $template): void
    {
        self::assertSame(
            OrderTrackingUrlResolver::isValidTemplate($template),
            CustomDelivery::isValidTrackingUrlTemplateWithoutCore($template),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function templates(): iterable
    {
        yield 'valid' => ['https://carrier.example/track?parcel=%ID%'];
        yield 'valid with port and fragment' => ['http://carrier.example:8080/t/%ID%#top'];
        yield 'no marker' => ['https://carrier.example/track'];
        yield 'script' => ['javascript:alert(1)//%ID%'];
        yield 'marker in the host' => ['https://%ID%.carrier.example/'];
        yield 'credentials' => ['https://carrier.example@attacker.example/%ID%'];
        yield 'backslash' => ['https://attacker.example\\.carrier.example/%ID%'];
        yield 'no-break space' => ["https://carrier.example/\u{00A0}%ID%"];
        yield 'protocol relative' => ['//carrier.example/%ID%'];
    }

    private function listener(): CustomDeliveryEvents
    {
        return new CustomDeliveryEvents($this->getService(ParserInterface::class), $this->mailer);
    }

    private function customDeliveryOrder(string $trackingNumber): Order
    {
        $order = $this->createFixtureFactory()->order(null, ['statusCode' => OrderStatus::CODE_SENT, 'deliveryModuleCode' => 'CustomDelivery']);
        $order->setDeliveryRef($trackingNumber)->save($this->getPropelConnection());

        return $order;
    }

    private function shipped(Order $order): OrderEvent
    {
        $event = new OrderEvent($order);
        $event->setPreviousStatusId($this->orderStatus(OrderStatus::CODE_PROCESSING)->getId());
        $event->setStatus($this->orderStatus(OrderStatus::CODE_SENT)->getId());

        return $event;
    }

    private function orderStatus(string $code): OrderStatus
    {
        return OrderStatusQuery::create()->findOneByCode($code) ?? throw new \RuntimeException("Order status '$code' is missing.");
    }
}
