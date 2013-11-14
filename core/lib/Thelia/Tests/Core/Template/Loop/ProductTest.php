<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Tests\Core\Template\Loop;

use Thelia\Model\ProductQuery;
use Thelia\Tests\Core\Template\Element\BaseLoopTestor;

use Thelia\Core\Template\Loop\Product;
use Propel\Runtime\ActiveQuery\Criteria;

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

    public function getTestedInstance()
    {
        return new Product($this->container);
    }

    public function getMandatoryArguments()
    {
        return array();
    }

    public function testSearchById()
    {
        $product = ProductQuery::create()->orderById(Criteria::ASC)->findOne();
        if(null === $product) {
            $product = new \Thelia\Model\Product();
            $product->setDefaultCategory(0);
            $product->setVisible(1);
            $product->setTitle('foo');
            $product->save();
        }

        $otherParameters = array(
            "visible" => "*",
        );

        $this->baseTestSearchById($product->getId(), $otherParameters);
    }

    public function testSearchLimit()
    {
        $this->baseTestSearchWithLimit(3);
    }
}
