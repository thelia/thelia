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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CustomerQuery;

/**
 * Class CustomerLogin.
 *
 * @author  Manuel Raynaud <manu@raynaud.io>
 */
class CustomerLogin extends BruteforceForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Email(),
                    new Callback($this->verifyExistingEmail(...)),
                ],
                'label' => Translator::getInstance()->trans('Please enter your email address'),
                'label_attr' => [
                    'for' => 'email',
                ],
            ])
            ->add('account', ChoiceType::class, [
                'constraints' => [
                    new Callback(
                        $this->verifyAccount(...),
                    ),
                ],
                'choices' => [
                    Translator::getInstance()->trans('No, I am a new customer.') => 0,
                    Translator::getInstance()->trans('Yes, I have a password :') => 1,
                ],
                'label_attr' => [
                    'for' => 'account',
                ],
                'data' => 0,
            ])
            ->add('password', PasswordType::class, [
                'constraints' => [
                    new NotBlank([
                        'groups' => ['existing_customer'],
                    ]),
                ],
                'label' => Translator::getInstance()->trans('Please enter your password'),
                'label_attr' => [
                    'for' => 'password',
                ],
                'required' => false,
            ])
            ->add('remember_me', CheckboxType::class, [
                'value' => 'yes',
                'label' => Translator::getInstance()->trans('Remember me ?'),
                'label_attr' => [
                    'for' => 'remember_me',
                ],
            ]);
    }

    /**
     * If the user select "Yes, I have a password", we check the password.
     */
    public function verifyAccount($value, ExecutionContextInterface $context): void
    {
        if (1 === $value) {
            $data = $context->getRoot()->getData();

            if (false === $data['password'] || (empty($data['password']) && '0' !== $data['password'])) {
                $context->getViolations()->add(new ConstraintViolation(
                    Translator::getInstance()->trans('This value should not be blank.'),
                    'account_password',
                    [],
                    $context->getRoot(),
                    'children[password].data',
                    'propertyPath',
                ));
            }
        }
    }

    /**
     * If the user select "I'am a new customer", we make sure is email address does not exit in the database.
     */
    public function verifyExistingEmail($value, ExecutionContextInterface $context): void
    {
        $data = $context->getRoot()->getData();

        if (0 === $data['account']) {
            $customer = CustomerQuery::create()->findOneByEmail($value);

            if ($customer) {
                $context->addViolation(Translator::getInstance()->trans("A user already exists with this email address. Please login or if you've forgotten your password, go to Reset Your Password."));
            }
        }
    }

    public static function getName(): string
    {
        return 'thelia_customer_login';
    }
}
