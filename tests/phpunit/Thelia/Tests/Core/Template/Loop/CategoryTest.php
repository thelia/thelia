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
