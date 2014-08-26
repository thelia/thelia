<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Tests\Core\Template\Loop;

use Thelia\Model\SaleQuery;
use Thelia\Tests\Core\Template\Element\BaseLoopTestor;

use Thelia\Core\Template\Loop\Sale;

/**
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 */
class SaleTest extends BaseLoopTestor
{
    public function getTestedClassName()
    {
        return 'Thelia\Core\Template\Loop\Sale';
    }

    public function getTestedInstance()
    {
        return new Sale($this->container);
    }

    public function getMandatoryArguments()
    {
        return array();
    }

    public function testSearchById()
    {
        $newsale = false;

        $sale = SaleQuery::create()->findOne();

        if (null === $sale) {
            $sale = new \Thelia\Model\Sale();
            $sale->setActive(1);
            $sale->setTitle('foo');
            $sale->save();

            $newsale = $sale;
        }

        $otherParameters = array(
            "active" => "*",
        );

        $this->baseTestSearchById($sale->getId(), $otherParameters);

        if ($newsale) $newsale->delete();
    }

    public function testSearchLimit()
    {
        $newsale = false;

        $count = SaleQuery::create()->count();

        if ($count < 1) {
            $sale = new \Thelia\Model\Sale();
            $sale->setActive(1);
            $sale->setTitle('foo');
            $sale->save();

            $newsale = $sale;
        }

        $this->baseTestSearchWithLimit(1);

        if ($newsale) $newsale->delete();
    }
}
