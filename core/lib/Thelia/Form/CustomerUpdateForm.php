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

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;

/**
 * Class CustomerUpdateForm.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CustomerUpdateForm extends BaseForm
{
    use AddressCountryValidationTrait;

    /**
     * @return void|null
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'update_logged_in_user',
                IntegerType::class// In a front office context, update the in-memory logged-in user data
            )
            ->add('company', TextType::class, [
                'label' => Translator::getInstance()->trans('Company'),
                'label_attr' => [
                    'for' => 'company',
                ],
                'required' => false,
            ])
            ->add('firstname', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('First Name'),
                'label_attr' => [
                    'for' => 'firstname',
                ],
            ])
            ->add('lastname', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('Last Name'),
                'label_attr' => [
                    'for' => 'lastname',
                ],
            ])
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Email(),
                ],
                'label' => Translator::getInstance()->trans('Email address'),
                'label_attr' => [
                    'for' => 'email',
                ],
            ])
            ->add('email_confirm', EmailType::class, [
                'constraints' => [
                    new Email(),
                    new Callback($this->verifyEmailField(...)),
                ],
                'label' => Translator::getInstance()->trans('Confirm Email address'),
                'label_attr' => [
                    'for' => 'email_confirm',
                ],
            ])
            ->add('password', TextType::class, [
                'label' => Translator::getInstance()->trans('Password'),
                'required' => false,
                'label_attr' => [
                    'for' => 'password',
                    'help' => Translator::getInstance()->trans('Leave blank to keep current customer password'),
                ],
            ])
            ->add('address1', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label_attr' => [
                    'for' => 'address',
                ],
                'label' => Translator::getInstance()->trans('Street Address '),
            ])
            ->add('address2', TextType::class, [
                'required' => false,
                'label' => Translator::getInstance()->trans('Address Line 2'),
                'label_attr' => [
                    'for' => 'address2',
                ],
            ])
            ->add('address3', TextType::class, [
                'required' => false,
                'label' => Translator::getInstance()->trans('Address Line 3'),
                'label_attr' => [
                    'for' => 'address3',
                ],
            ])
            ->add('phone', TextType::class, [
                'label' => Translator::getInstance()->trans('Phone'),
                'label_attr' => [
                    'for' => 'phone',
                ],
                'required' => false,
            ])
            ->add('cellphone', TextType::class, [
                'label' => Translator::getInstance()->trans('Cellphone'),
                'label_attr' => [
                    'for' => 'cellphone',
                ],
                'required' => false,
            ])
            ->add('zipcode', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Callback(
                        $this->verifyZipCode(...)),
                ],
                'label' => Translator::getInstance()->trans('Zip code'),
                'label_attr' => [
                    'for' => 'zipcode',
                ],
            ])
            ->add('city', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('City'),
                'label_attr' => [
                    'for' => 'city',
                ],
            ])
            ->add('country', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('Country'),
                'label_attr' => [
                    'for' => 'country',
                ],
            ])
            ->add('state', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Callback(
                        $this->verifyState(...)
                    ),
                ],
                'label' => Translator::getInstance()->trans('State *'),
                'label_attr' => [
                    'for' => 'state',
                ],
            ])
            ->add('discount', TextType::class, [
                'required' => false,
                'label' => Translator::getInstance()->trans('permanent discount (in percent)'),
                'label_attr' => [
                    'for' => 'discount',
                ],
            ])
            ->add('reseller', CheckboxType::class, [
                'required' => false,
                'label' => Translator::getInstance()->trans('Reseller'),
                'label_attr' => [
                    'for' => 'reseller',
                ],
            ])
            ->add('lang_id', IntegerType::class, [
                'required' => false,
                'label' => Translator::getInstance()->trans('Preferred language'),
                'label_attr' => [
                    'for' => 'lang_id',
                ],
            ]);
    }

    public function verifyEmailField($value, ExecutionContextInterface $context): void
    {
        $data = $context->getRoot()->getData();

        if (isset($data['email_confirm']) && $data['email'] != $data['email_confirm']) {
            $context->addViolation(
                Translator::getInstance()->trans('email confirmation is not the same as email field')
            );
        }
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName(): string
    {
        return 'thelia_customer_update';
    }
}
