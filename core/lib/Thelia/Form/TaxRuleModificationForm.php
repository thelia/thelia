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
use Thelia\Core\Translation\Translator;
use Thelia\Model\TaxRuleQuery;

class TaxRuleModificationForm extends TaxRuleCreationForm
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
                                    array($this, "verifyTaxRuleId"),
                                ),
                            )
                        ),
                    )
            ))
        ;
    }

    public function getName()
    {
        return "thelia_tax_rule_modification";
    }

    public function verifyTaxRuleId($value, ExecutionContextInterface $context)
    {
        $taxRule = TaxRuleQuery::create()
            ->findPk($value);

        if (null === $taxRule) {
            $context->addViolation(Translator::getInstance()->trans("Tax rule ID not found"));
        }
    }
}
