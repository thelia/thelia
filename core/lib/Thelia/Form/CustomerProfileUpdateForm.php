<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Form;

use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
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
            ->remove("state")
            // Remove Login Information
            ->remove("password")
            ->remove("password_confirm")
        ;

        $customerCanChangeEmail = ConfigQuery::read("customer_change_email");
        $emailConfirmation = ConfigQuery::read("customer_confirm_email");

        if (! $customerCanChangeEmail) {
            $currentOptions = $this->formBuilder->get("email")->getOptions();
            $currentOptions["constraints"] = [];
            $currentOptions["required"] = false;

            $this->formBuilder->remove("email")->add("email", "text", $currentOptions);
        }

        if ($this->formBuilder->has("email_confirm") && ! ($customerCanChangeEmail && $emailConfirmation)) {
            $this->formBuilder->remove("email_confirm");
        }
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
            $context->addViolation(Translator::getInstance()->trans("This email already exists."));
        }
    }

    public function getName()
    {
        return "thelia_customer_profile_update";
    }
}
