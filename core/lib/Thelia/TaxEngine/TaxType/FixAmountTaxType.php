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

namespace Thelia\TaxEngine\TaxType;

use Thelia\Core\Translation\Translator;
use Thelia\TaxEngine\BaseTaxType;
use Thelia\TaxEngine\TaxTypeRequirementDefinition;
use Thelia\Type\FloatType;

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
        return [
            new TaxTypeRequirementDefinition(
                'amount',
                new FloatType(),
                Translator::getInstance()->trans("Amount")
            )
        ];
    }

    public function getTitle()
    {
        return Translator::getInstance()->trans("Constant amount");
    }
}
