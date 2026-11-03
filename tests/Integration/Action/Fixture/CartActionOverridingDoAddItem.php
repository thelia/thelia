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

namespace Thelia\Tests\Integration\Action\Fixture;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Action\Cart;
use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Model\Cart as CartModel;
use Thelia\Model\CartItem;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\Tools\ProductPriceTools;

/**
 * A module extending the cart action the way 3.3 let it: doAddItem() redefined with the
 * signature of 3.3. The class has to load, and its method has to serve the additions.
 */
final class CartActionOverridingDoAddItem extends Cart
{
    public ?ProductPriceTools $receivedPrices = null;

    protected function doAddItem(
        EventDispatcherInterface $dispatcher,
        CartModel $cart,
        int $productId,
        ProductSaleElements $productSaleElements,
        float $quantity,
        ProductPriceTools $productPrices,
        ?BundleComposition $bundleComposition = null,
    ): CartItem {
        $this->receivedPrices = $productPrices;

        return parent::doAddItem($dispatcher, $cart, $productId, $productSaleElements, $quantity, $productPrices, $bundleComposition);
    }
}
