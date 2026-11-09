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

namespace Thelia\Domain\Catalog\Stock;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\ProductSaleElement\LowStockReachedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\Lang;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Tells the shop managers that a sale element is running low.
 *
 * Runs late so that a module can handle the alert its own way and stop the
 * propagation. A failure to send is logged and swallowed: the stock change that
 * raised the alert, often an order, must not fail because of an e-mail.
 */
final readonly class LowStockAlertMailer
{
    public const string MESSAGE_CODE = 'low_stock_alert';

    public function __construct(private MailerFactory $mailer)
    {
    }

    #[AsEventListener(event: TheliaEvents::PRODUCT_SALE_ELEMENTS_LOW_STOCK_REACHED, priority: -128)]
    public function onLowStockReached(LowStockReachedEvent $event): void
    {
        try {
            $pse = ProductSaleElementsQuery::create()->findPk($event->getProductSaleElementsId());
            $product = $pse?->getProduct();

            if (null === $pse || null === $product) {
                return;
            }

            $product->setLocale(Lang::getDefaultLanguage()->getLocale());

            $this->mailer->sendEmailToShopManagers(self::MESSAGE_CODE, [
                'pse_id' => (int) $pse->getId(),
                'product_id' => (int) $product->getId(),
                'product_ref' => (string) $product->getRef(),
                'pse_ref' => (string) $pse->getRef(),
                'product_title' => (string) $product->getTitle(),
                'quantity' => $event->getQuantity(),
                'threshold' => $event->getThreshold(),
                // So that the template writes the stock left with its unit (format_quantity): "4,25 kg".
                'quantity_decimals' => (int) $pse->getQuantityDecimals(),
                'unit_code' => $pse->getUnitCode(),
            ]);
        } catch (\Throwable $exception) {
            Tlog::getInstance()->error(\sprintf('The low stock alert of sale element %d was not sent: %s', $event->getProductSaleElementsId(), $exception->getMessage()));
        }
    }
}
