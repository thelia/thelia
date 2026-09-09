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

namespace Thelia\Tests\Integration\Action;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\ViewCheckEvent;
use Thelia\Model\Product;
use Thelia\Model\Sale;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The product page is resolved from its rewritten URL through the VIEW_CHECK
 * event. A product hidden by a reserved operation must answer the same 404 there
 * as everywhere else: the query filter alone leaves the page to render a null
 * product, which is a 500, not a 404.
 */
final class ReservedProductViewCheckTest extends ActionIntegrationTestCase
{
    public function testTheProductViewOfAHiddenProductIsNotFoundForAVisitor(): void
    {
        $product = $this->catalogProduct();
        $this->reservedSaleHiding($product);

        $this->expectException(NotFoundHttpException::class);
        $this->dispatch(new ViewCheckEvent('product', $product->getId()), TheliaEvents::VIEW_CHECK);
    }

    public function testTheProductViewOfAPubliclySoldProductStillAnswers(): void
    {
        $product = $this->catalogProduct();

        $this->dispatch(new ViewCheckEvent('product', $product->getId()), TheliaEvents::VIEW_CHECK);

        // Reaching this line is the assertion: no NotFoundHttpException was thrown.
        self::assertTrue(true);
    }

    private function catalogProduct(): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100, 'basePrice' => 100.0],
        );
    }

    private function reservedSaleHiding(Product $product): Sale
    {
        $sale = $this->factory->sale([
            'active' => true,
            'startDate' => new \DateTime('-1 hour'),
            'endDate' => new \DateTime('+1 day'),
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'hideProducts' => true,
        ]);
        $this->factory->saleProduct($sale, $product);
        $this->factory->saleCustomer($sale, $this->factory->customer($this->factory->customerTitle()));

        return $sale;
    }
}
