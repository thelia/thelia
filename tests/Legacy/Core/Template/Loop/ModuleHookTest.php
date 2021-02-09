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

use Thelia\Core\Template\Loop\ModuleHook;
use Thelia\Model\ModuleHookQuery;
use Thelia\Tests\Core\Template\Element\BaseLoopTestor;

/**
 * Class ModuleHookTest
 * @package Thelia\Tests\Core\Template\Loop
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHookTest extends BaseLoopTestor
{
    public function getTestedClassName()
    {
        return 'Thelia\Core\Template\Loop\ModuleHook';
    }

    public function getMandatoryArguments()
    {
        return ["backend_context" => 1];
    }

    public function testSearchByHookId()
    {
        $moduleHook = ModuleHookQuery::create()->findOne();
        if (null !== $moduleHook) {
            $this->baseTestSearchById($moduleHook->getId());
        }
    }
}
