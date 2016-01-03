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

use Thelia\Model\BrandQuery;
use Thelia\Tests\Core\Template\Element\BaseLoopTestor;
use Thelia\Core\Template\Loop\Brand;

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

    public function getTestedInstance()
    {
        return new Brand($this->container);
    }

    public function getMandatoryArguments()
    {
        return array();
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

        $otherParameters = array(
            "visible" => "*",
        );

        $this->baseTestSearchById($brand->getId(), $otherParameters);
    }

    public function testSearchLimit()
    {
        $this->baseTestSearchWithLimit(3);
    }
}
