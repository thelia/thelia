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

use Thelia\Model\CategoryQuery;
use Thelia\Tests\Core\Template\Element\BaseLoopTestor;

use Thelia\Core\Template\Loop\Category;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class CategoryTest extends BaseLoopTestor
{
    public function getTestedClassName()
    {
        return 'Thelia\Core\Template\Loop\Category';
    }

    public function getTestedInstance()
    {
        return new Category($this->container);
    }

    public function getMandatoryArguments()
    {
        return array();
    }

    public function testSearchById()
    {
        $category = CategoryQuery::create()->findOne();
        if (null === $category) {
            $category = new \Thelia\Model\Category();
            $category->setParent(0);
            $category->setVisible(1);
            $category->setTitle('foo');
            $category->save();
        }

        $otherParameters = array(
            "visible" => "*",
        );

        $this->baseTestSearchById($category->getId(), $otherParameters);
    }

    public function testSearchLimit()
    {
        $this->baseTestSearchWithLimit(3);
    }
}
