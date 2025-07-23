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

use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CustomerQuery;

/**
 * Class CustomerLostPasswordForm.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CustomerLostPasswordForm extends FirewallForm
{
    /**
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->formBuilder attribute :.
     *
     * $this->formBuilder->add("name", TextType::class)
     *   ->add("email", EmailType::class, array(
     *           "attr" => array(
     *               "class" => "field"
     *           ),
     *           "label" => "email",
     *           "constraints" => array(
     *               new \Symfony\Component\Validator\Constraints\NotBlank()
     *           )
     *       )
     *   )
     *   ->add('age', IntegerType::class);
     */
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Email(),
                    new Callback(
                        $this->verifyExistingEmail(...),
                    ),
                ],
                'label' => Translator::getInstance()->trans('Please enter your email address'),
                'label_attr' => [
                    'for' => 'forgot-email',
                ],
            ]);
    }

    public function verifyExistingEmail($value, ExecutionContextInterface $context): void
    {
        $customer = CustomerQuery::create()->findOneByEmail($value);

        if (null === $customer) {
            $context->addViolation(Translator::getInstance()->trans('This email does not exists'));
        }
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName(): string
    {
        return 'thelia_customer_lost_password';
    }
}
