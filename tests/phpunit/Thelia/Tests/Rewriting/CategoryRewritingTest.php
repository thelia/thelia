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

namespace Thelia\Tests\Rewriting;

use Thelia\Model\Category;

/**
 * Class CategoryRewritingTest
 * @package Thelia\Tests\Rewriting
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CategoryRewritingTest extends BaseRewritingObject
{
    /**
     * @return \Thelia\Model\Category
     */
    public function getObject()
    {
        return new Category();
    }
}
