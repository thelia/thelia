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
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CountryQuery;

class TaxRuleCreationForm extends BaseForm
{
    protected function buildForm($change_mode = false)
    {
        $this->formBuilder
            ->add("locale", "text", array(
                "constraints" => array(new NotBlank())
            ))
            ->add("country", "text", array(
                "constraints" => array(
                    new Constraints\Callback(
                        array(
                            "methods" => array(
                                array($this, "verifyCountry"),
                            ),
                        )
                    ),
                ),
            ))
        ;
    }

    public function getName()
    {
        return "thelia_tax_rule_creation";
    }

    public function verifyCountry($value, ExecutionContextInterface $context)
    {
        $country = CountryQuery::create()
            ->findOneByIsoalpha3($value);

        if (null === $country) {
            $context->addViolation("Country ISOALPHA3 not found");
        }
    }
}
