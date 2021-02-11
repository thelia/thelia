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
class PricePercentTaxType extends BaseTaxType
{
    public function setPercentage($percent)
    {
        $this->setRequirement('percent', $percent);

        return $this;
    }

    public function pricePercentRetriever()
    {
        return ($this->getRequirement("percent") * 0.01);
    }

    public function getRequirementsDefinition()
    {
        return [
            new TaxTypeRequirementDefinition(
                'percent',
                new FloatType(),
                Translator::getInstance()->trans("Percent")
            )
        ];
    }

    public function getTitle()
    {
        return Translator::getInstance()->trans("Percentage of the product price");
    }
}
