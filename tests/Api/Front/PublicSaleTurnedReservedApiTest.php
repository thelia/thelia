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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Sale\SaleCreateEvent;
use Thelia\Core\Event\Sale\SaleUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Product;
use Thelia\Model\Sale;
use Thelia\Test\ApiTestCase;

/**
 * An operation that ran publicly wrote its discount in the catalog. Once it is
 * reserved for named customers, a visitor reading the products it covers must not
 * read that discount any more, flagged as a promotion or not.
 */
final class PublicSaleTurnedReservedApiTest extends ApiTestCase
{
    public function testAVisitorNoLongerReadsTheDiscountOfAnOperationTurnedReserved(): void
    {
        $factory = $this->createFixtureFactory();
        $currency = $factory->currency();
        $product = $factory->product($factory->category(), $factory->taxRule(), $currency, ['basePrice' => 100.0]);
        $saleElementId = $product->getDefaultSaleElements()->getId();

        $sale = $this->dispatch(
            (new SaleCreateEvent())->setLocale('en_US')->setTitle('Operation')->setSaleLabel('OP'),
            TheliaEvents::SALE_CREATE,
        )->getSale();

        $this->dispatch($this->saleUpdate($sale, $product, $currency->getId(), Sale::AUDIENCE_MODE_PUBLIC, []), TheliaEvents::SALE_UPDATE);

        $saleElement = $this->readSaleElement($saleElementId);
        self::assertTrue($saleElement['promo'], 'The public operation discounts the sale element.');
        self::assertSame(90.0, (float) $saleElement['productPrices'][0]['promoPrice']);

        $customer = $factory->customer($factory->customerTitle());
        $this->dispatch(
            $this->saleUpdate($sale, $product, $currency->getId(), Sale::AUDIENCE_MODE_CUSTOMERS, [$customer->getId()]),
            TheliaEvents::SALE_UPDATE,
        );

        $saleElement = $this->readSaleElement($saleElementId);
        self::assertFalse($saleElement['promo']);
        self::assertSame(
            100.0,
            (float) $saleElement['productPrices'][0]['promoPrice'],
            'The reserved discount is readable by any visitor.',
        );
    }

    /**
     * @param list<int> $customerIds
     */
    private function saleUpdate(Sale $sale, Product $product, int $currencyId, int $audienceMode, array $customerIds): SaleUpdateEvent
    {
        $event = new SaleUpdateEvent($sale->getId());
        $event
            ->setLocale('en_US')
            ->setTitle('Operation')
            ->setSaleLabel('OP')
            ->setActive(true)
            ->setStartDate(new \DateTime('-1 day'))
            ->setEndDate(new \DateTime('+1 day'))
            ->setPriceOffsetType(Sale::OFFSET_TYPE_PERCENTAGE)
            ->setDisplayInitialPrice(true)
            ->setChapo('')
            ->setDescription('')
            ->setPostscriptum('')
            ->setPriceOffsets([$currencyId => '10'])
            ->setProducts([$product->getId()])
            ->setProductAttributes([])
            ->setAudienceMode($audienceMode)
            ->setHideProducts(false)
            ->setCountdownMode(Sale::COUNTDOWN_MODE_NONE)
            ->setCountdownLeadHours(null)
            ->setCustomerIds($customerIds);

        return $event;
    }

    /**
     * @template T of object
     *
     * @param T $event
     *
     * @return T
     */
    private function dispatch(object $event, string $eventName): object
    {
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        return $dispatcher->dispatch($event, $eventName);
    }

    /**
     * @return array<string, mixed>
     */
    private function readSaleElement(int $saleElementId): array
    {
        $response = $this->jsonRequest('GET', '/api/front/product_sale_elements/'.$saleElementId);
        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
