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

namespace Thelia\Domain\Taxation\TaxEngine\TaxType;

use Thelia\Core\Translation\Translator;
use Thelia\Domain\Taxation\TaxEngine\BaseTaxType;
use Thelia\Domain\Taxation\TaxEngine\TaxTypeRequirementDefinition;
use Thelia\Log\Tlog;
use Thelia\Model\FeatureProductQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Product;
use Thelia\Type\FloatType;
use Thelia\Type\ModelValidIdType;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class FeatureFixAmountTaxType extends BaseTaxType
{
    public function setFeature($featureId): static
    {
        $this->setRequirement('feature', $featureId);

        return $this;
    }

    public function fixAmountRetriever(Product $product): float
    {
        $taxAmount = 0;
        $featureId = $this->getRequirement('feature');

        $query = FeatureProductQuery::create()
            ->filterByProduct($product)
            ->filterByFeatureId($featureId)
            ->findOne();

        if (null !== $query) {
            if (null === $query->getFeatureAvId()) {
                $taxAmount = $query->getFreeTextValue(); // BC for old behavior
            } else {
                $locale = LangQuery::create()->findPk($this->getRequirement('lang'))->getLocale();
                $taxAmount = $query->getFeatureAv()->setLocale($locale)->getTitle();
            }

            $testFloat = new FloatType();

            if (!$testFloat->isValid($taxAmount)) {
                // We cannot modify "bad" (consider uninitialized) feature value in backOffice if we throw exception
                Tlog::getInstance()->error(Translator::getInstance()->trans('Feature value does not match FLOAT format'));

                return 0;
            }
        }

        return $taxAmount;
    }

    public function getRequirementsDefinition(): array
    {
        return [
            new TaxTypeRequirementDefinition(
                'feature',
                new ModelValidIdType('Feature'),
                Translator::getInstance()->trans('Feature'),
            ),
            new TaxTypeRequirementDefinition(
                'lang',
                new ModelValidIdType('Lang'),
                Translator::getInstance()->trans('Language'),
            ),
        ];
    }

    public function getTitle(): string
    {
        return Translator::getInstance()->trans("Constant amount found in one of the product's feature");
    }
}
