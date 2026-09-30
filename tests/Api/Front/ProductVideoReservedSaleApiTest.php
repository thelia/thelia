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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsProductVideo;
use Thelia\Model\Sale;
use Thelia\Test\ApiTestCase;

/**
 * A product hidden by a reserved operation keeps its videos out of reach of the
 * customers the operation does not name, and the combination links of those
 * videos with them: the links carry the video and combination identifiers of the
 * hidden product.
 */
final class ProductVideoReservedSaleApiTest extends ApiTestCase
{
    public function testTheVideoLinksOfAProductHiddenByAReservedSaleAreOutOfReachOfAnotherCustomer(): void
    {
        $factory = $this->createFixtureFactory();
        $currency = $factory->currency();
        $product = $factory->product($factory->category(), $factory->taxRule(), $currency);
        $combination = $factory->productSaleElement($product);
        $video = $factory->productVideo($product);

        $link = new ProductSaleElementsProductVideo();
        $link->setProductSaleElementsId($combination->getId())->setProductVideoId($video->getId())->save();

        $namedCustomer = $this->newCustomer();
        $this->hiddenReservedSaleOn($product, $namedCustomer, $currency);
        $token = $this->authenticateAsCustomer($this->newCustomer());

        self::assertSame(
            Response::HTTP_NOT_FOUND,
            $this->jsonRequest('GET', '/api/front/product_videos/'.$video->getId(), token: $token)->getStatusCode(),
            'The video itself is hidden from a customer the operation does not name.',
        );

        $collection = $this->jsonRequest(
            'GET',
            '/api/front/product_sale_elements_product_video?productSaleElements.product.id='.$product->getId(),
            token: $token,
        );
        $payload = json_decode((string) $collection->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $ids = array_column($payload['hydra:member'] ?? $payload['member'] ?? [], 'id');

        self::assertNotContains($link->getId(), $ids, 'The link of a video hidden by a reserved sale is listed to a customer it does not name.');

        $item = $this->jsonRequest('GET', '/api/front/product_sale_elements_product_video/'.$link->getId(), token: $token);
        self::assertSame(Response::HTTP_NOT_FOUND, $item->getStatusCode(), (string) $item->getContent());
    }

    public function testTheCustomerTheOperationNamesStillReadsTheVideoLinks(): void
    {
        $factory = $this->createFixtureFactory();
        $currency = $factory->currency();
        $product = $factory->product($factory->category(), $factory->taxRule(), $currency);
        $combination = $factory->productSaleElement($product);
        $video = $factory->productVideo($product);

        $link = new ProductSaleElementsProductVideo();
        $link->setProductSaleElementsId($combination->getId())->setProductVideoId($video->getId())->save();

        $namedCustomer = $this->newCustomer();
        $this->hiddenReservedSaleOn($product, $namedCustomer, $currency);

        $item = $this->jsonRequest(
            'GET',
            '/api/front/product_sale_elements_product_video/'.$link->getId(),
            token: $this->authenticateAsCustomer($namedCustomer),
        );
        self::assertSame(Response::HTTP_OK, $item->getStatusCode(), (string) $item->getContent());
    }

    private function hiddenReservedSaleOn(Product $product, Customer $customer, Currency $currency): Sale
    {
        $factory = $this->createFixtureFactory();
        $sale = $factory->sale([
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'hideProducts' => true,
            'active' => true,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
        ]);
        $factory->saleProduct($sale, $product);
        $factory->saleCustomer($sale, $customer);
        $factory->saleOffsetCurrency($sale, $currency, 10.0);

        return $sale;
    }

    private function newCustomer(): Customer
    {
        $factory = $this->createFixtureFactory();

        return $factory->customer($factory->customerTitle(), ['password' => 'password']);
    }
}
