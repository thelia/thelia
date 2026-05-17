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

namespace BackOfficeDefaultTwigBundle\Service\Pdf;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductAttributeCombinationQuery;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderProductTaxQuery;
use Thelia\Model\OrderQuery;
use Thelia\Tools\AddressFormat;
use Twig\Environment;

/**
 * Renders order invoice/delivery PDFs through native Twig templates while keeping
 * the legacy GENERATE_PDF event pipeline intact so third-party modules can still
 * subscribe with a higher priority to swap the rendering engine.
 */
final class OrderPdfRenderer
{
    public const KIND_INVOICE = 'invoice';
    public const KIND_DELIVERY = 'delivery';

    private const TEMPLATE_BY_KIND = [
        self::KIND_INVOICE => '@BackOfficeDefaultTwig/pdf/invoice.html.twig',
        self::KIND_DELIVERY => '@BackOfficeDefaultTwig/pdf/delivery.html.twig',
    ];

    public function __construct(
        private readonly Environment $twig,
        private readonly EventDispatcherInterface $events,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function render(int $orderId, string $kind, bool $browser): Response
    {
        if (!isset(self::TEMPLATE_BY_KIND[$kind])) {
            throw new \InvalidArgumentException(\sprintf('Unsupported PDF kind "%s"', $kind));
        }

        $order = OrderQuery::create()->findPk($orderId);
        if (!$order instanceof Order) {
            throw new NotFoundHttpException(\sprintf('Order %d not found', $orderId));
        }

        $locale = $this->resolveLocale();
        $context = $this->buildContext($order, $kind, $locale);

        $html = $this->twig->render(self::TEMPLATE_BY_KIND[$kind], $context);

        $fileName = match ($kind) {
            self::KIND_INVOICE => (string) ConfigQuery::read('pdf_invoice_file', 'invoice'),
            self::KIND_DELIVERY => (string) ConfigQuery::read('pdf_delivery_file', 'delivery'),
        };

        $event = new PdfEvent(
            $html,
            'P',
            'A4',
            $this->langCodeFor($locale),
        );
        $event->setTemplateName($fileName);
        $event->setFileName((string) $order->getRef());
        $event->setObject($order);

        try {
            $this->events->dispatch($event, TheliaEvents::GENERATE_PDF);
        } catch (\Throwable $exception) {
            Tlog::getInstance()->error(
                \sprintf('PDF generation failed for order %d (%s): %s', $orderId, $kind, $exception->getMessage()),
            );

            throw $exception;
        }

        if (!$event->hasPdf()) {
            throw new \RuntimeException('PDF rendering listener did not produce a binary output.');
        }

        $disposition = $browser ? 'inline' : 'attachment';
        $response = new Response($event->getPdf());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            \sprintf('%s; filename="%s-%s.pdf"', $disposition, $kind, (string) $order->getRef()),
        );

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(Order $order, string $kind, string $locale): array
    {
        $currency = $order->getCurrency();
        $currencySymbol = $currency?->getSymbol() ?? '';
        $currencyCode = $currency?->getCode() ?? '';
        $lastLegacyRoundingOrderId = (int) ConfigQuery::read('last_legacy_rounding_order_id', 0);

        $invoiceAddress = $order->getOrderAddressRelatedByInvoiceOrderAddressId();
        $deliveryAddress = $order->getOrderAddressRelatedByDeliveryOrderAddressId();

        $items = $this->buildItems($order, $lastLegacyRoundingOrderId);

        $totalItemsAmount = 0.0;
        $totalItemsTax = 0.0;
        $taxes = [];
        foreach ($items as $item) {
            $totalItemsAmount += $item['real_total_price'];
            $totalItemsTax += $item['real_total_price_tax'];
            $taxKey = $item['tax_rule_title'] ?? '';
            $taxes[$taxKey] = ($taxes[$taxKey] ?? 0.0) + ($item['real_price_tax'] * $item['quantity']);
        }

        $postageTax = (float) $order->getPostageTax();
        $postage = (float) $order->getPostage();
        $postageUntaxed = $postage - $postageTax;
        $discount = (float) $order->getDiscount();

        $taxTotal = 0.0;
        $totalTaxedAmount = $order->getTotalAmount($taxTotal, true, true);

        $customer = CustomerQuery::create()->findPk((int) $order->getCustomerId());
        $paymentModule = $this->fetchModule((int) $order->getPaymentModuleId(), $locale);
        $deliveryModule = $this->fetchModule((int) $order->getDeliveryModuleId(), $locale);

        return [
            'kind' => $kind,
            'order' => $order,
            'order_id' => (int) $order->getId(),
            'order_ref' => (string) $order->getRef(),
            'invoice_ref' => 'invoice' === $kind ? 'FA'.substr((string) $order->getRef(), 3) : (string) $order->getRef(),
            'invoice_date' => $order->getInvoiceDate() ?? $order->getCreatedAt(),
            'customer_ref' => $customer?->getRef() ?? '',
            'customer_id' => (int) $order->getCustomerId(),
            'items' => $items,
            'taxes' => $taxes,
            'invoice_address_html' => $this->formatAddress($invoiceAddress, $locale),
            'delivery_address_html' => $this->formatAddress($deliveryAddress, $locale),
            'invoice_address' => $invoiceAddress,
            'delivery_address' => $deliveryAddress,
            'payment_module_id' => (int) $order->getPaymentModuleId(),
            'payment_module_title' => $paymentModule?->getTitle() ?? '',
            'delivery_module_id' => (int) $order->getDeliveryModuleId(),
            'delivery_module_title' => $deliveryModule?->getTitle() ?? '',
            'currency_symbol' => $currencySymbol,
            'currency_code' => $currencyCode,
            'total_items_amount' => $totalItemsAmount,
            'total_items_tax' => $totalItemsTax,
            'postage' => $postage,
            'postage_tax' => $postageTax,
            'postage_untaxed' => $postageUntaxed,
            'discount' => $discount,
            'total_taxed_amount' => $totalTaxedAmount,
            'untaxed_total_with_untaxed_postage' => $totalItemsAmount + $postageUntaxed,
            'store' => $this->storeContext($locale),
            'locale' => $locale,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildItems(Order $order, int $lastLegacyRoundingOrderId): array
    {
        $items = [];

        $orderProducts = OrderProductQuery::create()
            ->filterByOrderId((int) $order->getId())
            ->orderById()
            ->find();

        foreach ($orderProducts as $orderProduct) {
            \assert($orderProduct instanceof OrderProduct);

            $quantity = (float) $orderProduct->getQuantity();
            [$tax, $promoTax] = $this->sumTaxes((int) $orderProduct->getId());

            if ((int) $orderProduct->getOrderId() <= $lastLegacyRoundingOrderId) {
                $totalTax = round($tax * $quantity, 2);
                $totalPromoTax = round($promoTax * $quantity, 2);
                $taxedPrice = (float) $orderProduct->getPrice() + $tax;
                $taxedPromoPrice = (float) $orderProduct->getPromoPrice() + $promoTax;
                $totalPrice = (float) $orderProduct->getPrice() * $quantity;
                $totalPromoPrice = (float) $orderProduct->getPromoPrice() * $quantity;
                $totalTaxedPrice = round($taxedPrice, 2) * $quantity;
                $totalTaxedPromoPrice = round($taxedPromoPrice, 2) * $quantity;
            } else {
                $tax = round($tax, 2);
                $promoTax = round($promoTax, 2);
                $totalTax = $tax * $quantity;
                $totalPromoTax = $promoTax * $quantity;
                $taxedPrice = round((float) $orderProduct->getPrice(), 2) + $tax;
                $taxedPromoPrice = round((float) $orderProduct->getPromoPrice(), 2) + $promoTax;
                $totalPrice = round((float) $orderProduct->getPrice(), 2) * $quantity;
                $totalPromoPrice = round((float) $orderProduct->getPromoPrice(), 2) * $quantity;
                $totalTaxedPrice = $taxedPrice * $quantity;
                $totalTaxedPromoPrice = $taxedPromoPrice * $quantity;
            }

            $wasInPromo = 1 === $orderProduct->getWasInPromo();

            $items[] = [
                'id' => (int) $orderProduct->getId(),
                'ref' => (string) $orderProduct->getProductRef(),
                'pse_ref' => (string) $orderProduct->getProductSaleElementsRef(),
                'title' => (string) $orderProduct->getTitle(),
                'quantity' => $quantity,
                'tax_rule_title' => (string) $orderProduct->getTaxRuleTitle(),
                'real_price' => $wasInPromo ? (float) $orderProduct->getPromoPrice() : (float) $orderProduct->getPrice(),
                'real_taxed_price' => $wasInPromo ? $taxedPromoPrice : $taxedPrice,
                'real_price_tax' => $wasInPromo ? $promoTax : $tax,
                'real_total_price' => $wasInPromo ? $totalPromoPrice : $totalPrice,
                'real_total_taxed_price' => $wasInPromo ? $totalTaxedPromoPrice : $totalTaxedPrice,
                'real_total_price_tax' => $wasInPromo ? $totalPromoTax : $totalTax,
                'attribute_combinations' => $this->loadCombinations((int) $orderProduct->getId()),
            ];
        }

        return $items;
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function sumTaxes(int $orderProductId): array
    {
        $tax = 0.0;
        $promoTax = 0.0;

        $taxes = OrderProductTaxQuery::create()
            ->filterByOrderProductId($orderProductId)
            ->find();

        foreach ($taxes as $taxRow) {
            $tax += (float) $taxRow->getAmount();
            $promoTax += (float) $taxRow->getPromoAmount();
        }

        return [$tax, $promoTax];
    }

    /**
     * @return list<array{title: string, value: string}>
     */
    private function loadCombinations(int $orderProductId): array
    {
        $combinations = [];
        $rows = OrderProductAttributeCombinationQuery::create()
            ->filterByOrderProductId($orderProductId)
            ->find();

        foreach ($rows as $row) {
            $combinations[] = [
                'title' => (string) $row->getAttributeTitle(),
                'value' => (string) $row->getAttributeAvailabilityTitle(),
            ];
        }

        return $combinations;
    }

    private function formatAddress(?OrderAddress $address, string $locale): string
    {
        if (!$address instanceof OrderAddress) {
            return '';
        }

        try {
            return AddressFormat::getInstance()->formatTheliaAddress($address, $locale);
        } catch (\Throwable) {
            return $this->plainAddressFallback($address);
        }
    }

    private function plainAddressFallback(OrderAddress $address): string
    {
        $lines = [];
        if ('' !== (string) $address->getCompany()) {
            $lines[] = (string) $address->getCompany();
        }
        $lines[] = trim(\sprintf('%s %s', (string) $address->getFirstname(), (string) $address->getLastname()));
        $lines[] = (string) $address->getAddress1();
        if ('' !== (string) $address->getAddress2()) {
            $lines[] = (string) $address->getAddress2();
        }
        if ('' !== (string) $address->getAddress3()) {
            $lines[] = (string) $address->getAddress3();
        }
        $lines[] = trim(\sprintf('%s %s', (string) $address->getZipcode(), (string) $address->getCity()));

        return '<p>'.implode('<br>', array_filter($lines, static fn (string $line) => '' !== $line)).'</p>';
    }

    private function fetchModule(int $moduleId, string $locale): ?Module
    {
        if (0 === $moduleId) {
            return null;
        }

        $module = ModuleQuery::create()->findPk($moduleId);
        if (!$module instanceof Module) {
            return null;
        }

        $module->setLocale($locale);

        return $module;
    }

    /**
     * @return array<string, mixed>
     */
    private function storeContext(string $locale): array
    {
        $countryId = (int) ConfigQuery::read('store_country', 0);
        $countryTitle = '';
        if ($countryId > 0) {
            $country = CountryQuery::create()->findPk($countryId);
            if ($country instanceof Country) {
                $country->setLocale($locale);
                $countryTitle = (string) $country->getTitle();
            }
        }

        return [
            'name' => (string) ConfigQuery::read('store_name', ''),
            'address1' => (string) ConfigQuery::read('store_address1', ''),
            'address2' => (string) ConfigQuery::read('store_address2', ''),
            'address3' => (string) ConfigQuery::read('store_address3', ''),
            'zipcode' => (string) ConfigQuery::read('store_zipcode', ''),
            'city' => (string) ConfigQuery::read('store_city', ''),
            'country' => $countryTitle,
            'business_id' => (string) ConfigQuery::read('store_business_id', ''),
            'phone' => (string) ConfigQuery::read('store_phone', ''),
            'email' => (string) ConfigQuery::read('store_email', ''),
        ];
    }

    private function resolveLocale(): string
    {
        if ($this->translator instanceof Translator) {
            $current = $this->translator->getLocale();
            if ('' !== $current) {
                return $current;
            }
        }

        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }

    private function langCodeFor(string $locale): string
    {
        $parts = explode('_', $locale);

        return strtolower($parts[0] ?? 'fr');
    }
}
