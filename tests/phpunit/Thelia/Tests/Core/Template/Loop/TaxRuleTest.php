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

use Thelia\Tests\Core\Template\Element\BaseLoopTestor;
use Thelia\Core\Template\Loop\TaxRule;
use Thelia\Model\TaxRuleQuery;

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

    public function getTestedInstance()
    {
        return new TaxRule($this->container);
    }

    public function getMandatoryArguments()
    {
        return array();
    }

    public function testSearchById()
    {
        $tr = TaxRuleQuery::create()->findOne();
        if (null === $tr) {
            $tr = new \Thelia\Model\TaxRule();
            $tr->setTitle('foo');
            $tr->save();
        }

        $this->baseTestSearchById($tr->getId(), array('force_return' => true));
    }
}
