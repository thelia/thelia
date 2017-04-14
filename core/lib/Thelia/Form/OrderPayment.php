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

use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\AddressQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Class OrderPayment
 * @package Thelia\Form
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderPayment extends FirewallForm
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
                            array($this, "verifyInvoiceAddress"),
                        ),
                    )),
                ),
            ))
            ->add("payment-module", "integer", array(
                "required" => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(array(
                        "methods" => array(
                            array($this, "verifyPaymentModule"),
                        ),
                    )),
                ),
            ))
            // Add terms & conditions
            ->add("agreed", "checkbox", array(
                "constraints" => array(
                    new Constraints\IsTrue(array("message" => Translator::getInstance()->trans("Please accept the Terms and conditions in order to register."))),
                ),
                "label" => "Agreed",
                "label_attr" => array(
                    "for" => "agreed",
                ),
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
            ->filterActivatedByTypeAndId(BaseModule::PAYMENT_MODULE_TYPE, $value)
            ->findOne();

        if (null === $module) {
            $context->addViolation(Translator::getInstance()->trans("Payment module ID not found"));
        } elseif (! $module->isPayementModule()) {
            $context->addViolation(
                sprintf(Translator::getInstance()->trans("payment module %s is not a Thelia\Module\PaymentModuleInterface"), $module->getCode())
            );
        }
    }

    public function getName()
    {
        return "thelia_order_payment";
    }
}
