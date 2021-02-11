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

namespace Thelia\Tests\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Loop\Product;
use Thelia\Model\ProductQuery;
use Thelia\Tests\Core\Template\Element\BaseLoopTestor;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class ProductTest extends BaseLoopTestor
{
    public function getTestedClassName()
    {
        return 'Thelia\Core\Template\Loop\Product';
    }

    public function getMandatoryArguments()
    {
        return [];
    }

    public function testSearchById()
    {
        $product = ProductQuery::create()->orderById(Criteria::ASC)->findOne();

        // ensure translation
        $product->getTranslation()
            ->setTitle("foo")
            ->save()
        ;

        if (null === $product) {
            $product = new \Thelia\Model\Product();
            $product->setDefaultCategory(0);
            $product->setVisible(1);
            $product->setTitle('foo');
            $product->save();
        }

        $otherParameters = [
            "visible" => "*",
        ];

        $this->baseTestSearchById($product->getId(), $otherParameters);
    }

    public function testSearchByIdComplex()
    {
        $product = ProductQuery::create()->orderById(Criteria::ASC)->findOne();

        if (null === $product) {
            $product = new \Thelia\Model\Product();
            $product->setDefaultCategory(0);
            $product->setVisible(1);
            $product->setTitle('foo');
            $product->save();
        }

        $otherParameters = [
            "visible" => "*",
            "complex" => 1
        ];

        $this->baseTestSearchById($product->getId(), $otherParameters);
    }

    public function testSearchLimit()
    {
        $this->baseTestSearchWithLimit(3);
    }
}
