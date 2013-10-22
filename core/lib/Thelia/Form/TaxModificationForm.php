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
namespace Thelia\Form;

use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Model\TaxQuery;

/**
 * Class TaxModificationForm
 * @package Thelia\Form
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class TaxModificationForm extends TaxCreationForm
{
    protected function buildForm()
    {
        parent::buildForm(true);

        $this->formBuilder
            ->add("id", "hidden", array(
                    "required" => true,
                    "constraints" => array(
                        new Constraints\NotBlank(),
                        new Constraints\Callback(
                            array(
                                "methods" => array(
                                    array($this, "verifyTaxId"),
                                ),
                            )
                        ),
                    )
            ))
        ;
    }

    public function getName()
    {
        return "thelia_tax_modification";
    }

    public function verifyTaxId($value, ExecutionContextInterface $context)
    {
        $tax = TaxQuery::create()
            ->findPk($value);

        if (null === $tax) {
            $context->addViolation("Tax ID not found");
        }
    }
}
