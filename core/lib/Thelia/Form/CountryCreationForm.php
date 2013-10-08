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

use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

class CountryCreationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("title", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Country title *"),
                "label_attr" => array(
                    "for" => "title"
                )
            ))
            ->add("locale", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label_attr" => array("for" => "locale_create")
            ))
            ->add("area", "text", array(
                "label" => Translator::getInstance()->trans("Country area"),
                "label_attr" => array(
                    "for" => "area"
                )
            ))
            ->add("isocode", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("ISO Code *"),
                "label_attr" => array(
                    "for" => "isocode"
                )
            ))
            ->add("isoalpha2", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Alpha code 2 *"),
                "label_attr" => array(
                    "for" => "isoalpha2"
                )
            ))
            ->add("isoalpha3", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Alpha code 3 *"),
                "label_attr" => array(
                    "for" => "isoalpha3"
                )
            ))
        ;
    }

    public function getName()
    {
        return "thelia_country_creation";
    }
}
