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

use Thelia\Core\Template\Loop\Brand;
use Thelia\Model\BrandQuery;
use Thelia\Tests\Core\Template\Element\BaseLoopTestor;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class BrandTest extends BaseLoopTestor
{
    public function getTestedClassName()
    {
        return 'Thelia\Core\Template\Loop\Brand';
    }

    public function getMandatoryArguments()
    {
        return [];
    }

    public function testSearchById()
    {
        $brand = BrandQuery::create()->findOne();
        if (null === $brand) {
            $brand = new \Thelia\Model\Brand();
            $brand->setVisible(1);
            $brand->setTitle('foo');
            $brand->save();
        }

        $otherParameters = [
            "visible" => "*",
        ];

        $this->baseTestSearchById($brand->getId(), $otherParameters);
    }

    public function testSearchLimit()
    {
        $this->baseTestSearchWithLimit(3);
    }
}
