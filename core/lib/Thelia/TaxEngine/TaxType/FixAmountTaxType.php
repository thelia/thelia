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

namespace Thelia\TaxEngine\TaxType;

use Thelia\Type\FloatType;
use Thelia\Core\Translation\Translator;
use Thelia\TaxEngine\BaseTaxType;
use Thelia\TaxEngine\TaxTypeRequirementDefinition;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class FixAmountTaxType extends BaseTaxType
{
    public function setAmount($amount)
    {
        $this->setRequirement('amount', $amount);

        return $this;
    }

    public function fixAmountRetriever(\Thelia\Model\Product $product)
    {
        return $this->getRequirement("amount");
    }

    public function getRequirementsDefinition()
    {
        return array(
            new TaxTypeRequirementDefinition(
                'amount',
                new FloatType(),
                Translator::getInstance()->trans("Amount")
            )
        );
    }

    public function getTitle()
    {
        return Translator::getInstance()->trans("Constant amount");
    }
}
