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
            new TaxTypeRequirementDefinition('amount', new FloatType())
        );
    }

    public function getTitle()
    {
        return Translator::getInstance()->trans("Constant amount");
    }
}
