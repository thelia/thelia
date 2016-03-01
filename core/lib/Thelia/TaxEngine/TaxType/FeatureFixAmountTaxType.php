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

use Thelia\Exception\TaxEngineException;
use Thelia\Model\FeatureProductQuery;
use Thelia\Model\Product;
use Thelia\Type\FloatType;
use Thelia\Type\ModelValidIdType;
use Thelia\Core\Translation\Translator;
use Thelia\TaxEngine\BaseTaxType;
use Thelia\TaxEngine\TaxTypeRequirementDefinition;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class FeatureFixAmountTaxType extends BaseTaxType
{
    public function setFeature($featureId)
    {
        $this->setRequirement('feature', $featureId);

        return $this;
    }

    public function fixAmountRetriever(Product $product)
    {
        $taxAmount = 0;
        $featureId = $this->getRequirement("feature");

        $query = FeatureProductQuery::create()
            ->filterByProduct($product)
            ->filterByFeatureId($featureId)
            ->findOne();

        if (null !== $query) {
            $taxAmount = $query->getFreeTextValue();

            $testInt = new FloatType();
            if (!$testInt->isValid($taxAmount)) {
                throw new TaxEngineException(
                    Translator::getInstance()->trans('Feature value does not match FLOAT format'),
                    TaxEngineException::FEATURE_BAD_EXPECTED_VALUE
                );
            }
        }

        return $taxAmount;
    }

    public function getRequirementsDefinition()
    {
        return array(
            new TaxTypeRequirementDefinition(
                'feature',
                new ModelValidIdType('Feature'),
                Translator::getInstance()->trans("Feature")
            )
        );
    }

    public function getTitle()
    {
        return Translator::getInstance()->trans("Constant amount found in one of the product's feature");
    }
}
