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

use Thelia\Core\Template\Loop\Hook;
use Thelia\Model\HookQuery;
use Thelia\Tests\Core\Template\Element\BaseLoopTestor;

/**
 * Class HookTest
 * @package Thelia\Tests\Core\Template\Loop
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookTest extends BaseLoopTestor
{
    public function getTestedClassName()
    {
        return 'Thelia\Core\Template\Loop\Hook';
    }

    public function getTestedInstance()
    {
        return new Hook($this->container);
    }

    public function getMandatoryArguments()
    {
        return array("backend_context" => 1);
    }

    public function testSearchByHookId()
    {
        $hook = HookQuery::create()->findOne();

        $this->baseTestSearchById($hook->getId());
    }
}
