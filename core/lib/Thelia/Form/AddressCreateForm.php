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
                "label" => Translator::getInstance()->trans("Address label *"),
                "label_attr" => array(
                    "for" => "label_create"
                ),
                "required" => true
            ))
            ->add("title", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Title"),
                "label_attr" => array(
                    "for" => "title_create"
                )
            ))
            ->add("firstname", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Firstname"),
                "label_attr" => array(
                    "for" => "firstname_create"
                )
            ))
            ->add("lastname", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Lastname"),
                "label_attr" => array(
                    "for" => "lastname_create"
                )
            ))
            ->add("address1", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Street Address"),
                "label_attr" => array(
                    "for" => "address1_create"
                )
            ))
            ->add("address2", "text", array(                
                "label" => Translator::getInstance()->trans("Additional address"),
                "label_attr" => array(
                    "for" => "address2_create"
                )
            ))
            ->add("address3", "text", array(
                "label" => Translator::getInstance()->trans("Additional address"),
                "label_attr" => array(
                    "for" => "address3_create"
                )
            ))
            ->add("zipcode", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Zip code"),
                "label_attr" => array(
                    "for" => "zipcode_create"
                )
            ))
            ->add("city", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("City"),
                "label_attr" => array(
                    "for" => "city_create"
                )
            ))
            ->add("country", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Country"),
                "label_attr" => array(
                    "for" => "country_create"
                )
            ))
            ->add("phone", "text", array(
                "label" => Translator::getInstance()->trans("Phone"),
                "label_attr" => array(
                    "for" => "phone_create"
                )
            ))
            ->add("cellphone", "text", array(
                "label" => Translator::getInstance()->trans("Cellphone"),
                "label_attr" => array(
                    "for" => "cellphone_create"
                )
            ))
            ->add("company", "text", array(
                "label" => Translator::getInstance()->trans("Compagny"),
                "label_attr" => array(
                    "for" => "company_create"
                )
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
