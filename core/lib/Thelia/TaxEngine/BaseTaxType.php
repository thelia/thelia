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

namespace Thelia\TaxEngine;

use Thelia\Exception\TaxEngineException;
use Thelia\Model\Product;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
abstract class BaseTaxType
{
    /**
     * A var <-> value array which contains TaxtType requirements (e.g. parameters)
     *
     * @var array
     */
    protected $requirements = array();

    /**
     * For a price percent tax type, return the percentage (e.g. 20 for 20%) of the product price
     * to use in tax calculation.
     *
     * For other tax types, this method shoud return 0.
     *
     * @return number
     */
    public function pricePercentRetriever()
    {
        return 0;
    }

    /**
     * For constant amount tax type, return the absolute amount to use in tax calculation.
     *
     * For other tax types, this method shoud return 0.
     *
     * @return number
     */
    public function fixAmountRetriever(Product $product)
    {
        return 0;
    }

    /**
     * Returns the requirements definition of this tax type. This is an array of
     * TaxTypeRequirementDefinition, which defines the name and the type of
     * the requirements. Example :
     *
     * array(
     *    'percent' => new FloatType()
     * );
     *
     * @return array of TaxTypeRequirementDefinition
     */
    public function getRequirementsDefinition()
    {
        return array();
    }

    /**
     * @return the name of this tax type.
     */
    abstract public function getTitle();

    public function calculate(Product $product, $untaxedPrice)
    {
        return $untaxedPrice * $this->pricePercentRetriever() + $this->fixAmountRetriever($product);
    }

    /**
     * @throws TaxEngineException
     * @return array              Return the requirements array.
     */
    public function getRequirements()
    {
        return $this->requirements;
    }

    public function loadRequirements($requirementsValues)
    {
        $requirements = $this->getRequirementsDefinition();

        if (!is_array($requirements)) {
            throw new TaxEngineException('getRequirementsDefinition must return an array', TaxEngineException::TAX_TYPE_BAD_ABSTRACT_METHOD);
        }

        foreach ($requirements as $requirement) {
            $requirementName = $requirement->getName();

            if (! array_key_exists($requirementName, $requirementsValues)) {
                throw new TaxEngineException('Cannot load requirements : requirement value for `' . $requirementName . '` not found', TaxEngineException::TAX_TYPE_REQUIREMENT_NOT_FOUND);
            }

            if (! $requirement->isValueValid($requirementsValues[$requirementName])) {
                throw new TaxEngineException('Requirement value for `' . $requirementName . '` does not match required type', TaxEngineException::TAX_TYPE_BAD_REQUIREMENT_VALUE);
            }

            $this->requirements[$requirementName] = $requirementsValues[$requirementName];
        }
    }

    public function setRequirement($key, $value)
    {
        $this->requirements[$key] = $value;

        return $this;
    }

    public function getRequirement($key)
    {
        if (!array_key_exists($key, $this->requirements)) {
            throw new TaxEngineException('Requirement value for `' . $key . '` does not exists in BaseTaxType::$requirements', TaxEngineException::UNDEFINED_REQUIREMENT_VALUE);
        }

        return $this->requirements[$key];
    }
}
