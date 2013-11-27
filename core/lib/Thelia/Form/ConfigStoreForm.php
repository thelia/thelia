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
use Thelia\Core\Translation\Translator;

class ConfigStoreForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("store_name", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => Translator::getInstance()->trans('Store name'),
                "label_attr" => array(
                        "for" => "store_name"
                )
            ))
            ->add("store_email", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Email()
                ),
                "label" => Translator::getInstance()->trans('Store email address'),
                "label_attr" => array(
                    "for" => "store_email"
                )
            ))
            ->add("store_business_id", "text", array(
                "label" => Translator::getInstance()->trans('Business ID'),
                "label_attr" => array(
                    "for" => "store_business_id"
                ),
                "required" => false
            ))
            ->add("store_phone", "text", array(
                "label" => Translator::getInstance()->trans("Phone"),
                "label_attr" => array(
                    "for" => "store_phone"
                ),
                "required" => false
            ))
            ->add("store_fax", "text", array(
                "label" => Translator::getInstance()->trans("Fax"),
                "label_attr" => array(
                    "for" => "store_fax"
                ),
                "required" => false
            ))
            ->add("store_address1", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Street Address"),
                "label_attr" => array(
                    "for" => "store_address1"
                )
            ))
            ->add("store_address2", "text", array(
                "label" => Translator::getInstance()->trans("Address Line 2"),
                "label_attr" => array(
                    "for" => "store_address2"
                ),
                "required" => false
            ))
            ->add("store_address3", "text", array(
                "label" => Translator::getInstance()->trans("Address Line 3"),
                "label_attr" => array(
                    "for" => "store_address3"
                ),
                "required" => false
            ))
            ->add("store_zipcode", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Zip code"),
                "label_attr" => array(
                    "for" => "store_zipcode"
                )
            ))
            ->add("store_city", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => Translator::getInstance()->trans("City"),
                "label_attr" => array(
                    "for" => "store_city"
                )
            ))
            ->add("store_country", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Country"),
                "label_attr" => array(
                    "for" => "store_country"
                )
            ))
            ;
    }

    public function getName()
    {
        return "thelia_configuration_store";
    }
}
