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
 * Class OrderDelivery
 * @package Thelia\Form
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderDelivery extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("delivery-address", "integer", array(
                "required" => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(array(
                        "methods" => array(
                            array($this, "verifyDeliveryAddress")
                        )
                    ))
                )
            ))
            ->add("delivery-module", "integer", array(
                "required" => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(array(
                        "methods" => array(
                            array($this, "verifyDeliveryModule")
                        )
                    ))
                )
            ));
    }

    public function verifyDeliveryAddress($value, ExecutionContextInterface $context)
    {
        $address = AddressQuery::create()
            ->findPk($value);

        if (null === $address) {
            $context->addViolation(Translator::getInstance()->trans("Address ID not found"));
        }
    }

    public function verifyDeliveryModule($value, ExecutionContextInterface $context)
    {
        $module = ModuleQuery::create()
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE)
            ->filterByActivate(1)
            ->filterById($value)
            ->findOne();

        if (null === $module) {
            $context->addViolation(Translator::getInstance()->trans("Delivery module ID not found"));
        }

        $moduleReflection = new \ReflectionClass($module->getFullNamespace());
        if ($moduleReflection->isSubclassOf("Thelia\Module\DeliveryModuleInterface") === false) {
            $context->addViolation(
                sprintf(Translator::getInstance()->trans("delivery module %s is not a Thelia\Module\DeliveryModuleInterface"), $module->getCode())
            );
        }
    }

    public function getName()
    {
        return "thelia_order_delivery";
    }
}
