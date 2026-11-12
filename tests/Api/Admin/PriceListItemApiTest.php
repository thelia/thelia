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

namespace Thelia\Tests\Api\Admin;

use Thelia\Model\PriceListItem;
use Thelia\Model\PriceListItemQuery;

final class PriceListItemApiTest extends PriceListApiTestCase
{
    public function testAPriceLineIsCreatedReadBackAndUpdated(): void
    {
        $list = $this->priceList();
        $saleElement = $this->saleElement();
        $token = $this->pricingAdminToken();

        $created = $this->decode($this->jsonRequest('POST', '/api/admin/price_list_items', $this->payload($list->getId(), $saleElement->getId(), ['minQuantity' => 10, 'price' => 9.5]), $token), 201);

        $row = PriceListItemQuery::create()->findPk($created['id']);
        self::assertSame(10, $row->getMinQuantity());
        self::assertSame('9.500000', $row->getPrice());
        self::assertNull($row->getDiscountPercent());

        $read = $this->decode($this->jsonRequest('GET', '/api/admin/price_list_items/'.$created['id'], token: $token));
        self::assertEquals(9.5, $read['price']);
        self::assertArrayNotHasKey('discountPercent', $read);

        $this->decode($this->jsonRequest('PATCH', '/api/admin/price_list_items/'.$created['id'], ['price' => 8.25], $token, 'merge-patch+json'));
        self::assertSame('8.250000', PriceListItemQuery::create()->findPk($created['id'])->getPrice());

        $this->decode($this->jsonRequest('PATCH', '/api/admin/price_list_items/'.$created['id'], ['price' => null, 'discountPercent' => 15], $token, 'merge-patch+json'));
        $row = PriceListItemQuery::create()->findPk($created['id']);
        self::assertNull($row->getPrice());
        self::assertSame('15.000000', $row->getDiscountPercent());
    }

    public function testTheMinimumQuantityIsOneWhenTheWriteLeavesItOut(): void
    {
        $list = $this->priceList();
        $saleElement = $this->saleElement();

        $created = $this->decode($this->jsonRequest('POST', '/api/admin/price_list_items', $this->payload($list->getId(), $saleElement->getId(), ['discountPercent' => 100]), $this->pricingAdminToken()), 201);

        self::assertSame(1, PriceListItemQuery::create()->findPk($created['id'])->getMinQuantity());
        self::assertSame('100.000000', PriceListItemQuery::create()->findPk($created['id'])->getDiscountPercent());
    }

    public function testAPriceOfZeroIsAccepted(): void
    {
        $this->decode($this->jsonRequest('POST', '/api/admin/price_list_items', $this->payload($this->priceList()->getId(), $this->saleElement()->getId(), ['price' => 0]), $this->pricingAdminToken()), 201);
    }

    public function testALineWithBothAmountsOrNoneIsRefused(): void
    {
        $list = $this->priceList();
        $saleElement = $this->saleElement();
        $token = $this->pricingAdminToken();

        $both = $this->jsonRequest('POST', '/api/admin/price_list_items', $this->payload($list->getId(), $saleElement->getId(), ['price' => 9, 'discountPercent' => 10]), $token);
        self::assertContains('discountPercent', $this->violationPaths($both));

        $none = $this->jsonRequest('POST', '/api/admin/price_list_items', $this->payload($list->getId(), $saleElement->getId()), $token);
        self::assertContains('price', $this->violationPaths($none));

        self::assertSame(0, PriceListItemQuery::create()->filterByPriceListId($list->getId())->count());
    }

    public function testANegativePriceIsRefused(): void
    {
        $response = $this->jsonRequest('POST', '/api/admin/price_list_items', $this->payload($this->priceList()->getId(), $this->saleElement()->getId(), ['price' => -0.01]), $this->pricingAdminToken());

        self::assertContains('price', $this->violationPaths($response));
    }

    public function testADiscountOutsideZeroExcludedToOneHundredIsRefused(): void
    {
        $list = $this->priceList();
        $saleElement = $this->saleElement();
        $token = $this->pricingAdminToken();

        foreach ([0, -5, 100.01, 250] as $discount) {
            $response = $this->jsonRequest('POST', '/api/admin/price_list_items', $this->payload($list->getId(), $saleElement->getId(), ['discountPercent' => $discount]), $token);
            self::assertContains('discountPercent', $this->violationPaths($response), 'refused: '.$discount);
        }
    }

    public function testAMinimumQuantityBelowOneIsRefused(): void
    {
        $list = $this->priceList();
        $saleElement = $this->saleElement();
        $token = $this->pricingAdminToken();

        foreach ([0, -3] as $quantity) {
            $response = $this->jsonRequest('POST', '/api/admin/price_list_items', $this->payload($list->getId(), $saleElement->getId(), ['price' => 5, 'minQuantity' => $quantity]), $token);
            self::assertContains('minQuantity', $this->violationPaths($response), 'refused: '.$quantity);
        }
    }

    public function testASecondLineForTheSameListSaleElementAndQuantityIsRefused(): void
    {
        $list = $this->priceList();
        $saleElement = $this->saleElement();
        $token = $this->pricingAdminToken();
        $this->decode($this->jsonRequest('POST', '/api/admin/price_list_items', $this->payload($list->getId(), $saleElement->getId(), ['price' => 5, 'minQuantity' => 3]), $token), 201);

        $duplicate = $this->jsonRequest('POST', '/api/admin/price_list_items', $this->payload($list->getId(), $saleElement->getId(), ['price' => 4, 'minQuantity' => 3]), $token);
        self::assertContains('minQuantity', $this->violationPaths($duplicate));

        $otherQuantity = $this->jsonRequest('POST', '/api/admin/price_list_items', $this->payload($list->getId(), $saleElement->getId(), ['price' => 4, 'minQuantity' => 4]), $token);
        self::assertSame(201, $otherQuantity->getStatusCode(), (string) $otherQuantity->getContent());
        self::assertSame(2, PriceListItemQuery::create()->filterByPriceListId($list->getId())->count());
    }

    public function testALineIsDeletedWithItsListByCascade(): void
    {
        $list = $this->priceList();
        $item = (new PriceListItem())->setPriceListId($list->getId())->setProductSaleElementsId($this->saleElement()->getId())->setMinQuantity(1)->setPrice('3.000000');
        $item->save();

        self::assertSame(204, $this->jsonRequest('DELETE', '/api/admin/price_lists/'.$list->getId(), token: $this->pricingAdminToken())->getStatusCode());
        self::assertNull(PriceListItemQuery::create()->findPk($item->getId()));
    }

    public function testAProfileWithoutThePricingRightCannotReadNorWriteTheLines(): void
    {
        $list = $this->priceList();
        $saleElement = $this->saleElement();
        $item = (new PriceListItem())->setPriceListId($list->getId())->setProductSaleElementsId($saleElement->getId())->setMinQuantity(1)->setPrice('3.000000');
        $item->save();
        $token = $this->catalogOnlyAdminToken();

        self::assertSame(403, $this->jsonRequest('GET', '/api/admin/price_list_items', token: $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('GET', '/api/admin/price_list_items/'.$item->getId(), token: $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/price_list_items', $this->payload($list->getId(), $saleElement->getId(), ['price' => 1, 'minQuantity' => 2]), $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('PATCH', '/api/admin/price_list_items/'.$item->getId(), ['price' => 1], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame(403, $this->jsonRequest('DELETE', '/api/admin/price_list_items/'.$item->getId(), token: $token)->getStatusCode());
        self::assertSame('3.000000', PriceListItemQuery::create()->findPk($item->getId())->getPrice());
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function payload(int $priceListId, int $saleElementId, array $extra = []): array
    {
        return [
            'priceList' => '/api/admin/price_lists/'.$priceListId,
            'productSaleElements' => '/api/admin/product_sale_elements/'.$saleElementId,
        ] + $extra;
    }
}
