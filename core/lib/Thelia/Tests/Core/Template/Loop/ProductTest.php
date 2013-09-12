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
        $product = ProductQuery::create()->findOne();

        $loop = new Product($this->container);
        $loop->initializeArgs(array(
            "type" => "product",
            "name" => "product",
            "id" => $product->getId(),
        ));
        $loopResults = $loop->exec($pagination);

        $this->assertEquals(1, $loopResults->getCount());

        $substitutions = $loopResults->current()->getVarVal();

        $this->assertEquals($product->getId(), $substitutions['ID']);
    }
}
