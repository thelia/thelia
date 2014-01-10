<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\TaxEngine\TaxType;

use Thelia\Exception\TaxEngineException;
use Thelia\Model\Product;
use Thelia\Type\TypeInterface;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
abstract class BaseTaxType
{
    protected $requirements = null;

    abstract public function pricePercentRetriever();

    abstract public function fixAmountRetriever(Product $product);

    abstract public function getRequirementsList();

    abstract public function getTitle();

    public function calculate(Product $product, $untaxedPrice)
    {
        return $untaxedPrice * $this->pricePercentRetriever() + $this->fixAmountRetriever($product);
    }

    public function loadRequirements($requirementsValues)
    {
        $this->requirements = $this->getRequirementsList();

        if (!is_array($this->requirements)) {
            throw new TaxEngineException('getRequirementsList must return an array', TaxEngineException::TAX_TYPE_BAD_ABSTRACT_METHOD);
        }

        foreach ($this->requirements as $requirement => $requirementType) {
            if (!$requirementType instanceof TypeInterface) {
                throw new TaxEngineException('getRequirementsList must return an array of TypeInterface', TaxEngineException::TAX_TYPE_BAD_ABSTRACT_METHOD);
            }

            if (!array_key_exists($requirement, $requirementsValues)) {
                throw new TaxEngineException('Cannot load requirements : requirement value for `' . $requirement . '` not found', TaxEngineException::TAX_TYPE_REQUIREMENT_NOT_FOUND);
            }

            if (!$requirementType->isValid($requirementsValues[$requirement])) {
                throw new TaxEngineException('Requirement value for `' . $requirement . '` does not match required type', TaxEngineException::TAX_TYPE_BAD_REQUIREMENT_VALUE);
            }

            $this->requirements[$requirement] = $requirementsValues[$requirement];
        }
    }

    public function getRequirement($key)
    {
        if ($this->requirements === null) {
            throw new TaxEngineException('Requirements are empty in BaseTaxType::getRequirement', TaxEngineException::UNDEFINED_REQUIREMENTS);
        }

        if (!array_key_exists($key, $this->requirements)) {
            throw new TaxEngineException('Requirement value for `' . $key . '` does not exists in BaseTaxType::$requirements', TaxEngineException::UNDEFINED_REQUIREMENT_VALUE);
        }

        return $this->requirements[$key];
    }
}
