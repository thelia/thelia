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

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;

/**
 * Class CustomerCreateForm.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CustomerCreateForm extends AddressCreateForm
{
    protected function buildForm()
    {
        parent::buildForm();

        $this->formBuilder
            // Remove From Address create form
            ->remove('label')
            ->remove('is_default')

            // Add
            ->add('auto_login', IntegerType::class)
            // Add Email address
            ->add('email', EmailType::class, [
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Email(),
                    new Constraints\Callback(
                            [$this, 'verifyExistingEmail']
                        ),
                ],
                'label' => Translator::getInstance()->trans('Email Address'),
                'label_attr' => [
                    'for' => 'email',
                ],
            ])
            // Add Login Information
            ->add('password', PasswordType::class, [
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Length(['min' => ConfigQuery::read('password.length', 4)]),
                ],
                'label' => Translator::getInstance()->trans('Password'),
                'label_attr' => [
                    'for' => 'password',
                ],
            ])
            ->add('password_confirm', PasswordType::class, [
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Length(['min' => ConfigQuery::read('password.length', 4)]),
                    new Constraints\Callback([$this, 'verifyPasswordField']),
                ],
                'label' => Translator::getInstance()->trans('Password confirmation'),
                'label_attr' => [
                    'for' => 'password_confirmation',
                ],
            ])
            // Add Newsletter
            ->add('newsletter', CheckboxType::class, [
                'label' => Translator::getInstance()->trans('I would like to receive the newsletter or the latest news.'),
                'label_attr' => [
                    'for' => 'newsletter',
                ],
                'required' => false,
            ])
            ->add('lang_id', IntegerType::class, [
                'required' => false,
                'label' => Translator::getInstance()->trans('Preferred language'),
                'label_attr' => [
                    'for' => 'lang_id',
                ],
            ])
        ;

        //confirm email
        if (\intval(ConfigQuery::read('customer_confirm_email', 0))) {
            $this->formBuilder->add('email_confirm', EmailType::class, [
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Email(),
                    new Constraints\Callback([$this, 'verifyEmailField']),
                ],
                'label' => Translator::getInstance()->trans('Confirm Email Address'),
                'label_attr' => [
                    'for' => 'email_confirm',
                ],
            ]);
        }
    }

    public function verifyPasswordField($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();

        if ($data['password'] != $data['password_confirm']) {
            $context->addViolation(Translator::getInstance()->trans('password confirmation is not the same as password field'));
        }
    }

    public function verifyEmailField($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();

        if ($data['email'] != $data['email_confirm']) {
            $context->addViolation(Translator::getInstance()->trans('email confirmation is not the same as email field'));
        }
    }

    public function verifyExistingEmail($value, ExecutionContextInterface $context)
    {
        $customer = CustomerQuery::getCustomerByEmail($value);
        if ($customer) {
            $context->addViolation(Translator::getInstance()->trans('This email already exists.'));
        }
    }

    public static function getName()
    {
        return 'thelia_customer_create';
    }
}
