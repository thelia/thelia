<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
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
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Form;

use Symfony\Component\Validator\Constraints;
use Thelia\Model\ConfigQuery;
use Thelia\Core\Translation\Translator;

/**
 * Class CustomerUpdateForm
 * @package Thelia\Form
 * @author Christophe Laffont <claffont@openstudio.fr>
 */
class CustomerUpdateForm extends CustomerCreation
{

    protected function buildForm()
    {
        parent::buildForm();


        $this->formBuilder
            ->remove("auto_login")
            // Remove From Personal Informations
            ->remove("phone")
            ->remove("cellphone")
            // Remove Delivery Informations
            ->remove("company")
            ->remove("address1")
            ->remove("address2")
            ->remove("address3")
            ->remove("city")
            ->remove("zipcode")
            ->remove("country")
            // Remove Login Information
            ->remove("password")
            ->remove("password_confirm")
            // Remove Terms & conditions
            ->remove("agreed")

            // Add Newsletter
            ->add("newsletter", "checkbox", array(
                "label" => "I would like to receive the newsletter our the latest news.",
                "label_attr" => array(
                    "for" => "newsletter"
                ),
                "required" => false
            ));
    }

    public function getName()
    {
        return "thelia_customer_update";
    }
}
