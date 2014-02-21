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
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\AddressQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Class OrderPayment
 * @package Thelia\Form
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderPayment extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("invoice-address", "integer", array(
                "required" => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(array(
                        "methods" => array(
                            array($this, "verifyInvoiceAddress")
                        )
                    ))
                )
            ))
            ->add("payment-module", "integer", array(
                "required" => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(array(
                        "methods" => array(
                            array($this, "verifyPaymentModule")
                        )
                    ))
                )
            ));
    }

    public function verifyInvoiceAddress($value, ExecutionContextInterface $context)
    {
        $address = AddressQuery::create()
            ->findPk($value);

        if (null === $address) {
            $context->addViolation(Translator::getInstance()->trans("Address ID not found"));
        }
    }

    public function verifyPaymentModule($value, ExecutionContextInterface $context)
    {
        $module = ModuleQuery::create()
            ->filterByType(BaseModule::PAYMENT_MODULE_TYPE)
            ->filterByActivate(1)
            ->filterById($value)
            ->findOne();

        if (null === $module) {
            $context->addViolation("Payment module ID not found");
        }

        $moduleReflection = new \ReflectionClass($module->getFullNamespace());
        if ($moduleReflection->isSubclassOf("Thelia\Module\PaymentModuleInterface") === false) {
            $context->addViolation(
                Translator::getInstance()->trans(sprintf("delivery module %s is not a Thelia\Module\PaymentModuleInterface", $module->getCode()))
            );
        }
    }

    public function getName()
    {
        return "thelia_order_payment";
    }
}
