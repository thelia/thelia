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

use Thelia\Core\Template\Loop\TaxRule;
use Thelia\Model\TaxRuleQuery;
use Thelia\Tests\Core\Template\Element\BaseLoopTestor;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class TaxRuleTest extends BaseLoopTestor
{
    public function getTestedClassName()
    {
        return 'Thelia\Core\Template\Loop\TaxRule';
    }

    public function getMandatoryArguments()
    {
        return [];
    }

    public function testSearchById()
    {
        $tr = TaxRuleQuery::create()->findOne();
        if (null === $tr) {
            $tr = new \Thelia\Model\TaxRule();
            $tr->setTitle('foo');
            $tr->save();
        }

        $this->baseTestSearchById($tr->getId(), ['force_return' => true]);
    }
}
