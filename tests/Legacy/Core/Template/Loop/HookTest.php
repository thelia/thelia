<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Core\Template\Loop;

use Thelia\Model\HookQuery;
use Thelia\Tests\Core\Template\Element\BaseLoopTestor;

/**
 * Class HookTest.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookTest extends BaseLoopTestor
{
    public function getTestedClassName()
    {
        return 'Thelia\Core\Template\Loop\Hook';
    }

    public function getMandatoryArguments()
    {
        return ['backend_context' => 1];
    }

    public function testSearchByHookId(): void
    {
        $hook = HookQuery::create()->findOne();

        $this->baseTestSearchById($hook->getId());
    }
}
