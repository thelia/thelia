<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Rewriting;

use Thelia\Model\Product;

/**
 * Class ProductRewriteTest.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ProductRewriteTest extends BaseRewritingObject
{
    /**
     * @return mixed an instance of Product, Folder, Content or Category Model
     */
    public function getObject()
    {
        $product = new Product();
        $product->setRef(uniqid());

        return $product;
    }
}
