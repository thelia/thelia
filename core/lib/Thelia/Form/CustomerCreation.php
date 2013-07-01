<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                          */
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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints;
use Thelia\Model\ConfigQuery;


class CustomerCreation extends BaseForm
{

    protected function buildForm()
    {
        $this->form
            ->add("firstname", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => "firstname"
            ))
            ->add("lastname", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => "lastname"
            ))
            ->add("email", "email", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Email()
                ),
                "label" => "email"
            ))
            ->add("address1", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => "address"
            ))
            ->add("address2", "text", array(
                "label" => "Address Line 2"
            ))
            ->add("address3", "text", array(
                "label" => "Address Line 3"
            ))
            ->add("zipcode", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                )
            ))
            ->add("country", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                )
            ))
            ->add("password", "password", array(
                "constraints" => array(
                    new Constraints\Length(array("min" => ConfigQuery::read("password.length", 4)))
                )
            ))

        ;
    }

    public function getName()
    {
        return "customerCreation";
    }
}