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

use Thelia\Type\TypeInterface;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
abstract class BaseTaxType
{
    protected $requirements = null;

    public abstract function calculate($untaxedPrice);

    public abstract function getRequirementsList();

    public function loadRequirements($requirementsValues)
    {
        $this->requirements = $this->getRequirementsList();

        if(!is_array($this->requirements)) {
            //@todo throw sg
            exit('err_1');
        }

        foreach($this->requirements as $requirement => $requirementType) {
            if(!$requirementType instanceof TypeInterface) {
                //@todo throw sg
                exit('err_2');
            }

            if(!array_key_exists($requirement, $requirementsValues)) {
                //@todo throw sg
                exit('err_3');
            }

            if(!$requirementType->isValid($requirementsValues[$requirement])) {
                //@todo throw sg
                exit('err_4');
            }

            $this->requirements[$requirement] = $requirementsValues[$requirement];
        }
    }

    public function getRequirement($key)
    {
        if($this->requirements === null) {
            //@todo throw sg
            exit('err_5');
        }

        if(!array_key_exists($key, $this->requirements)) {
            //@todo throw sg
            exit('err_6');
        }

        return $this->requirements[$key];
    }
}
