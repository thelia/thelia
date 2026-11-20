<?php

declare(strict_types=1);

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

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Addressing\Service\AddressAccess;
use Thelia\Model\AddressQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Class OrderPayment.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderPayment extends FirewallForm
{
    public function __construct(private readonly ?AddressAccess $addressAccess = null)
    {
    }

    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('invoice-address', IntegerType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Callback(
                        $this->verifyInvoiceAddress(...),
                    ),
                ],
            ])
            ->add('payment-module', IntegerType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Callback(
                        $this->verifyPaymentModule(...),
                    ),
                ],
            ])
            // Add terms & conditions
            ->add('agreed', CheckboxType::class, [
                'constraints' => [
                    new IsTrue(['message' => Translator::getInstance()->trans('Please accept the Terms and conditions in order to register.')]),
                ],
                'label' => 'Agreed',
                'label_attr' => [
                    'for' => 'agreed',
                ],
            ]);
    }

    public function verifyInvoiceAddress($value, ExecutionContextInterface $context): void
    {
        $session = $this->request->hasSession() ? $this->request->getSession() : null;
        $customer = $session instanceof Session ? $session->getCustomerUser() : null;

        $address = null !== $customer && null !== $this->addressAccess
            ? $this->addressAccess->find($customer, (int) $value)
            : AddressQuery::create()->findPk($value);

        if (null === $address) {
            $context->addViolation(Translator::getInstance()->trans('Address ID not found'));
        }
    }

    public function verifyPaymentModule($value, ExecutionContextInterface $context): void
    {
        $module = ModuleQuery::create()
            ->filterActivatedByTypeAndId(BaseModule::PAYMENT_MODULE_TYPE, $value)
            ->findOne();

        if (null === $module) {
            $context->addViolation(Translator::getInstance()->trans('Payment module ID not found'));
        } elseif (!$module->isPayementModule()) {
            $context->addViolation(
                \sprintf(Translator::getInstance()->trans('payment module %s is not a Thelia\\Module\\PaymentModuleInterface'), $module->getCode()),
            );
        }
    }

    public static function getName(): string
    {
        return 'thelia_order_payment';
    }
}
