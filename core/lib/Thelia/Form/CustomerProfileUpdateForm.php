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

use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Model\CustomerQuery;

/**
 * Class CustomerProfileUpdateForm
 * @package Thelia\Form
 * @author Christophe Laffont <claffont@openstudio.fr>
 */
class CustomerProfileUpdateForm extends CustomerCreateForm
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
                "label" => Translator::getInstance()->trans('I would like to receive the newsletter or the latest news.'),
                "label_attr" => array(
                    "for" => "newsletter"
                ),
                "required" => false
            ));
    }

    /**
     * @param $value
     * @param ExecutionContextInterface $context
     */
    public function verifyExistingEmail($value, ExecutionContextInterface $context)
    {
        $customer = CustomerQuery::getCustomerByEmail($value);
        // If there is already a customer for this email address and if the customer is different from the current user, do a violation
        if ($customer && $customer->getId() != $this->getRequest()->getSession()->getCustomerUser()->getId()) {
            $context->addViolation("This email already exists.");
        }
    }

    public function getName()
    {
        return "thelia_customer_profil_update";
    }
}
