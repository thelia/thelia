<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Form;

use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
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
            ->add("delivery-address", IntegerType::class, [
                "required" => true,
                "constraints" => [
                    new Constraints\NotBlank(),
                    new Constraints\Callback(
                            [$this, "verifyDeliveryAddress"]
                    ),
                ],
            ])
            ->add("delivery-module", IntegerType::class, [
                "required" => true,
                "constraints" => [
                    new Constraints\NotBlank(),
                    new Constraints\Callback(
                            [$this, "verifyDeliveryModule"]
                    ),
                ],
            ]);
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
            ->filterActivatedByTypeAndId(BaseModule::DELIVERY_MODULE_TYPE, $value)
            ->findOne();

        if (null === $module) {
            $context->addViolation(Translator::getInstance()->trans("Delivery module ID not found"));
        } elseif (! $module->isDeliveryModule()) {
            $context->addViolation(
                sprintf(Translator::getInstance()->trans("delivery module %s is not a Thelia\Module\DeliveryModuleInterface"), $module->getCode())
            );
        }
    }

    public static function getName()
    {
        return "thelia_order_delivery";
    }
}
