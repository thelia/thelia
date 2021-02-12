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

use Thelia\Tests\Core\Template\Element\BaseLoopTestor;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class AttributeCombinationTest extends BaseLoopTestor
{
    public function getTestedClassName()
    {
        return 'Thelia\Core\Template\Loop\AttributeCombination';
    }

    public function getMandatoryArguments()
    {
        return ['product_sale_elements' => 1];
    }
}
