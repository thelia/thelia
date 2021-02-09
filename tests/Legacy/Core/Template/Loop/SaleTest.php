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

use Thelia\Core\Template\Loop\Sale;
use Thelia\Model\SaleQuery;
use Thelia\Tests\Core\Template\Element\BaseLoopTestor;

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

    public function getMandatoryArguments()
    {
        return ["active" => "*"];
    }

    public function testSearchById()
    {
        $sale = SaleQuery::create()->findOne();

        $this->baseTestSearchById($sale->getId());
    }

    public function testSearchLimit()
    {
        $this->baseTestSearchWithLimit(3);
    }
}
