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

namespace Thelia\Domain\Order\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Service\OrderStatusCatalog;
use Thelia\Domain\Order\Service\OrderTrackingUrlResolver;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\MessageQuery;
use Thelia\Model\ModuleI18nQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;

/**
 * Tells the customer their order has left, with the carrier, the tracking number
 * and the tracking page when the order has them.
 *
 * The e-mail goes out when the order ENTERS the "sent" status (a custom status
 * declared equivalent to it counts), never because it is in it: saving an order
 * that is already sent, or moving it between two statuses that both stand for
 * "sent", sends nothing. Correcting the tracking number afterwards does not send
 * it again either; the link shown to the customer follows the new number.
 *
 * Runs at 32, after Thelia\Action\Order (128) has committed the new status and
 * after the history listeners (64), so the history shows the status change before
 * the e-mail. Nothing here reads a session: a status changed by a payment
 * notification, the API or a script sends the e-mail the same way.
 */
final readonly class SendShippingEmailListener
{
    public const string MESSAGE_CODE = 'order_shipped';

    public const string ENABLED_CONFIG_KEY = 'order_shipped_email_enabled';

    public function __construct(
        private MailerFactory $mailer,
        private OrderStatusCatalog $statusCatalog,
        private OrderTrackingUrlResolver $trackingUrlResolver,
    ) {
    }

    /**
     * On by default: a shop without the setting row sends the e-mail.
     */
    public static function isEnabled(): bool
    {
        return '0' !== (string) ConfigQuery::read(self::ENABLED_CONFIG_KEY, '1');
    }

    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_STATUS, priority: 32)]
    public function onOrderStatusUpdate(OrderEvent $event): void
    {
        if (!$this->entersTheSentStatus($event) || !self::isEnabled()) {
            return;
        }

        $order = $event->getOrder();
        $customer = $order->getCustomer();

        // The message is seeded by the install and the update script; a shop that
        // removed it gets no e-mail rather than a failed status change.
        if (null === $customer || null === MessageQuery::create()->findOneByName(self::MESSAGE_CODE)) {
            return;
        }

        $trackingNumber = trim((string) $order->getDeliveryRef());

        $this->mailer->sendEmailToCustomer(self::MESSAGE_CODE, $customer, [
            'order_id' => $order->getId(),
            'order_ref' => $order->getRef(),
            'delivery_ref' => '' === $trackingNumber ? null : $trackingNumber,
            'tracking_url' => $this->trackingUrlResolver->resolve($order),
            'carrier' => $this->carrierOf($order, $customer->getCustomerLang()->getLocale()),
        ]);
    }

    private function entersTheSentStatus(OrderEvent $event): bool
    {
        $previousStatusId = $event->getPreviousStatusId();
        $newStatusId = $event->getStatus();

        if (null === $previousStatusId || null === $newStatusId || $previousStatusId === $newStatusId) {
            return false;
        }

        if (OrderStatus::CODE_SENT !== $this->statusCatalog->get($newStatusId)?->getEffectiveCode()) {
            return false;
        }

        return OrderStatus::CODE_SENT !== $this->statusCatalog->get($previousStatusId)?->getEffectiveCode();
    }

    /**
     * The carrier name in the language of the customer: the title of the delivery
     * module (its code when it has no title in that language), or the title the
     * order kept when that module was uninstalled.
     */
    private function carrierOf(Order $order, string $locale): ?string
    {
        $module = $order->getModuleRelatedByDeliveryModuleId();
        // Read from the i18n table: setLocale(), and getTranslation() too through
        // addModuleI18n(), would leave the shared module instance in the customer's
        // language for the rest of the request.
        $title = null !== $module
            ? (ModuleI18nQuery::create()->filterById($module->getId())->filterByLocale($locale)->findOne()?->getTitle() ?: $module->getCode())
            : $order->getDeliveryModuleTitle();
        $title = trim((string) $title);

        return '' === $title ? null : $title;
    }
}
