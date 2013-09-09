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
                "label" => "address name",
                "required" => true
            ))
            ->add("title", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => "title"
            ))
            ->add("firstname", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => "first name"
            ))
            ->add("lastname", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => "last name"
            ))
            ->add("address1", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => "address"
            ))
            ->add("address2", "text", array(
                "label" => "address (line 2)"
            ))
            ->add("address3", "text", array(
                "label" => "address (line 3)"
            ))
            ->add("zipcode", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => "zipcode"
            ))
            ->add("city", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => "city"
            ))
            ->add("country", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => "country"
            ))
            ->add("phone", "text", array(
                "label" => "phone"
            ))
            ->add("cellphone", "text", array(
                "label" => "cellphone"
            ))
            ->add("company", "text", array(
                "label" => "company"
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