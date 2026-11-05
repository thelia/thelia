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

use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ApiTestCase;

/**
 * The sale mode at zero stock, the restock date and the low stock threshold of a
 * combination, and the preorder mark of an order line, on the API.
 */
final class ProductStockSettingsApiTest extends ApiTestCase
{
    public function testTheAdminWritesAndReadsTheStockSettings(): void
    {
        $token = $this->authenticateAsAdmin();
        $combination = $this->combination();

        $response = $this->jsonRequest('PATCH', '/api/admin/product_sale_elements/'.$combination->getId(), [
            'outOfStockPolicy' => 2,
            'preorderLimit' => 10,
            'restockDate' => '2027-03-15',
            'lowStockThreshold' => 20,
        ], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        $stored = ProductSaleElementsQuery::create()->findPk($combination->getId());
        self::assertSame(2, $stored->getOutOfStockPolicy());
        self::assertSame(10.0, $stored->getPreorderLimit());
        self::assertSame('2027-03-15', $stored->getRestockDate()->format('Y-m-d'));
        self::assertSame(20.0, $stored->getLowStockThreshold());

        $read = self::decodeJson($this->jsonRequest('GET', '/api/admin/product_sale_elements/'.$combination->getId(), token: $token));
        self::assertSame(2, $read['outOfStockPolicy']);
        self::assertSame(10.0, (float) $read['preorderLimit']);
        self::assertSame(20.0, (float) $read['lowStockThreshold']);
        self::assertStringStartsWith('2027-03-15', $read['restockDate']);
    }

    public function testAnUnknownModeIsRefused(): void
    {
        $response = $this->jsonRequest('PATCH', '/api/admin/product_sale_elements/'.$this->combination()->getId(), [
            'outOfStockPolicy' => 5,
        ], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testANegativeLimitOrThresholdIsRefused(): void
    {
        $token = $this->authenticateAsAdmin();
        $combination = $this->combination();

        foreach (['preorderLimit', 'lowStockThreshold'] as $field) {
            $response = $this->jsonRequest('PATCH', '/api/admin/product_sale_elements/'.$combination->getId(), [$field => -1], $token, 'merge-patch+json');

            self::assertSame(422, $response->getStatusCode(), $field.': '.$response->getContent());
        }
    }

    public function testTheFrontReadsTheModeAndTheDateButNeverTheThreshold(): void
    {
        $combination = $this->combination();
        $combination->setOutOfStockPolicy(2)->setPreorderLimit(10.0)->setRestockDate(new \DateTime('2027-03-15'))->setLowStockThreshold(20.0)->save();

        $response = $this->jsonRequest('GET', '/api/front/product_sale_elements/'.$combination->getId());

        self::assertJsonResponseSuccessful($response);
        $read = self::decodeJson($response);
        self::assertSame(2, $read['outOfStockPolicy']);
        self::assertSame(10.0, (float) $read['preorderLimit']);
        self::assertStringStartsWith('2027-03-15', $read['restockDate']);
        self::assertArrayNotHasKey('lowStockThreshold', $read);
    }

    public function testAnOrderLineSaysWhetherItWasSoldOnPreorder(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $order = $factory->order();
        $line = $factory->orderProduct($order);
        $line->setIsPreorder(1)->setRestockDate(new \DateTime('2027-04-01'))->save();

        $read = self::decodeJson($this->jsonRequest('GET', '/api/admin/order_products/'.$line->getId(), token: $token));

        self::assertTrue($read['isPreorder'], json_encode($read));
        self::assertStringStartsWith('2027-04-01', $read['restockDate']);
    }

    public function testAnOrderLineCannotBeTurnedIntoAPreorderThroughTheApi(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $line = $factory->orderProduct($factory->order());

        $this->jsonRequest('PATCH', '/api/admin/order_products/'.$line->getId(), ['isPreorder' => true, 'restockDate' => '2027-04-01'], $token, 'merge-patch+json');

        $stored = \Thelia\Model\OrderProductQuery::create()->findPk($line->getId());
        self::assertSame(0, $stored->getIsPreorder());
        self::assertNull($stored->getRestockDate());
    }

    private function combination(): \Thelia\Model\ProductSaleElements
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());

        return $factory->productSaleElement($product);
    }
}
