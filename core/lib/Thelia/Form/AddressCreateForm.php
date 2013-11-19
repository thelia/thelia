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

/**
 * Class AddressCreateForm
 * @package Thelia\Form
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class AddressCreateForm extends BaseForm
{

    /**
     *
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->formBuilder attribute :
     *
     * $this->formBuilder->add("name", "text")
     *   ->add("email", "email", array(
     *           "attr" => array(
     *               "class" => "field"
     *           ),
     *           "label" => "email",
     *           "constraints" => array(
     *               new \Symfony\Component\Validator\Constraints\NotBlank()
     *           )
     *       )
     *   )
     *   ->add('age', 'integer');
     *
     * @return null
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add("label", "text", array(
                    "constraints" => array(
                        new NotBlank()
                    ),
                    "label" => Translator::getInstance()->trans("Address label"),
                    "label_attr" => array(
                        "for" => "address_label"
                    )
                ))
            ->add("title", "text", array(
                    "constraints" => array(
                        new Constraints\NotBlank()
                    ),
                    "label" => Translator::getInstance()->trans("Title"),
                    "label_attr" => array(
                        "for" => "title"
                    )
                ))
            ->add("firstname", "text", array(
                    "constraints" => array(
                        new Constraints\NotBlank()
                    ),
                    "label" => Translator::getInstance()->trans("First Name"),
                    "label_attr" => array(
                        "for" => "firstname"
                    )
                ))
            ->add("lastname", "text", array(
                    "constraints" => array(
                        new Constraints\NotBlank()
                    ),
                    "label" => Translator::getInstance()->trans("Last Name"),
                    "label_attr" => array(
                        "for" => "lastname"
                    )
                ))
            ->add("company", "text", array(
                    "label" => Translator::getInstance()->trans("Company Name"),
                    "label_attr" => array(
                        "for" => "company"
                    ),
                    "required" => false
                ))
            ->add("address1", "text", array(
                    "constraints" => array(
                        new Constraints\NotBlank()
                    ),
                    "label" => Translator::getInstance()->trans("Street Address"),
                    "label_attr" => array(
                        "for" => "address1"
                    )
                ))
            ->add("address2", "text", array(
                    "label" => Translator::getInstance()->trans("Address Line 2"),
                    "label_attr" => array(
                        "for" => "address2"
                    ),
                    "required" => false
                ))
            ->add("address3", "text", array(
                    "label" => Translator::getInstance()->trans("Address Line 3"),
                    "label_attr" => array(
                        "for" => "address3"
                    ),
                    "required" => false
                ))
            ->add("city", "text", array(
                    "constraints" => array(
                        new Constraints\NotBlank()
                    ),
                    "label" => Translator::getInstance()->trans("City"),
                    "label_attr" => array(
                        "for" => "city"
                    )
                ))
            ->add("zipcode", "text", array(
                    "constraints" => array(
                        new Constraints\NotBlank()
                    ),
                    "label" => Translator::getInstance()->trans("Zip code"),
                    "label_attr" => array(
                        "for" => "zipcode"
                    )
                ))
            ->add("country", "text", array(
                    "constraints" => array(
                        new Constraints\NotBlank()
                    ),
                    "label" => Translator::getInstance()->trans("Country"),
                    "label_attr" => array(
                        "for" => "country"
                    )
                ))
            // Phone
            ->add("phone", "text", array(
                    "label" => Translator::getInstance()->trans("Phone"),
                    "label_attr" => array(
                        "for" => "phone"
                    ),
                    "required" => false
                ))
            ->add("cellphone", "text", array(
                    "label" => Translator::getInstance()->trans("Cellphone"),
                    "label_attr" => array(
                        "for" => "cellphone"
                    ),
                    "required" => false
                ))
            // Default address
            ->add("is_default", "checkbox", array(
                    "label" => Translator::getInstance()->trans("Make this address as my primary address"),
                    "label_attr" => array(
                        "for" => "default_address"
                    ),
                    "required" => false
                ))
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return "thelia_address_creation";
    }
}
