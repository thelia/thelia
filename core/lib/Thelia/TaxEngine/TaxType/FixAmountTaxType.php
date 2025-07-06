<?php

declare(strict_types=1);

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
use Thelia\Model\Product;
use Thelia\TaxEngine\BaseTaxType;
use Thelia\TaxEngine\TaxTypeRequirementDefinition;
use Thelia\Type\FloatType;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class FixAmountTaxType extends BaseTaxType
{
    public function setAmount($amount): static
    {
        $this->setRequirement('amount', $amount);

        return $this;
    }

    public function fixAmountRetriever(Product $product): float
    {
        return $this->getRequirement('amount');
    }

    public function getRequirementsDefinition(): array
    {
        return [
            new TaxTypeRequirementDefinition(
                'amount',
                new FloatType(),
                Translator::getInstance()->trans('Amount')
            ),
        ];
    }

    public function getTitle(): string
    {
        return Translator::getInstance()->trans('Constant amount');
    }
}
