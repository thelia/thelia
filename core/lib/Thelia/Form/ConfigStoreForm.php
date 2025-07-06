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

use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;

class ConfigStoreForm extends BaseForm
{
    protected function buildForm(): void
    {
        $tr = Translator::getInstance();

        $this->formBuilder
            ->add(
                'store_name',
                TextType::class,
                [
                    'data' => ConfigQuery::getStoreName(),
                    'constraints' => [new NotBlank()],
                    'label' => $tr->trans('Store name'),
                    'attr' => [
                        'placeholder' => $tr->trans('Used in your store front'),
                    ],
                ]
            )
            ->add(
                'store_description',
                TextType::class,
                [
                    'data' => ConfigQuery::getStoreDescription(),
                    'required' => false,
                    'label' => $tr->trans('Store description'),
                    'attr' => [
                        'placeholder' => $tr->trans('Used in your store front'),
                    ],
                ]
            )
            ->add(
                'store_email',
                TextType::class,
                [
                    'data' => ConfigQuery::getStoreEmail(),
                    'constraints' => [
                        new NotBlank(),
                        new Email(),
                    ],
                    'label' => $tr->trans('Store email address'),
                    'attr' => [
                        'placeholder' => $tr->trans('Contact and sender email address'),
                    ],
                    'label_attr' => [
                        'help' => $tr->trans('This is the contact email address, and the sender email of all e-mails sent by your store.'),
                    ],
                ]
            )
            ->add(
                'store_notification_emails',
                TextType::class,
                [
                    'data' => ConfigQuery::read('store_notification_emails'),
                    'constraints' => [
                        new NotBlank(),
                        new Callback(
                            $this->checkEmailList(...)
                        ),
                    ],
                    'label' => $tr->trans('Email addresses of notification recipients'),
                    'attr' => [
                        'placeholder' => $tr->trans('A comma separated list of email addresses'),
                    ],
                    'label_attr' => [
                        'help' => $tr->trans('This is a comma separated list of email addresses where store notifications (such as order placed) are sent.'),
                    ],
                ]
            )
            ->add(
                'store_business_id',
                TextType::class,
                [
                    'data' => ConfigQuery::read('store_business_id'),
                    'label' => $tr->trans('Business ID'),
                    'required' => false,
                    'attr' => [
                        'placeholder' => $tr->trans('Store Business Identification Number (SIRET, etc).'),
                    ],
                ]
            )
            ->add(
                'store_phone',
                TextType::class,
                [
                    'data' => ConfigQuery::read('store_phone'),
                    'label' => $tr->trans('Phone'),
                    'required' => false,
                    'attr' => [
                        'placeholder' => $tr->trans('The store phone number.'),
                    ],
                ]
            )
            ->add(
                'store_fax',
                TextType::class,
                [
                    'data' => ConfigQuery::read('store_fax'),
                    'label' => $tr->trans('Fax'),
                    'required' => false,
                    'attr' => [
                        'placeholder' => $tr->trans('The store fax number.'),
                    ],
                ]
            )
            ->add(
                'store_address1',
                TextType::class,
                [
                    'data' => ConfigQuery::read('store_address1'),
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => $tr->trans('Street Address'),
                    'attr' => [
                        'placeholder' => $tr->trans('Address.'),
                    ],
                ]
            )
            ->add(
                'store_address2',
                TextType::class,
                [
                    'data' => ConfigQuery::read('store_address2'),
                    'required' => false,
                    'attr' => [
                        'placeholder' => $tr->trans('Additional address information'),
                    ],
                ]
            )
            ->add(
                'store_address3',
                TextType::class,
                [
                    'data' => ConfigQuery::read('store_address3'),
                    'required' => false,
                    'attr' => [
                        'placeholder' => $tr->trans('Additional address information'),
                    ],
                ]
            )
            ->add(
                'store_zipcode',
                TextType::class,
                [
                    'data' => ConfigQuery::read('store_zipcode'),
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => $tr->trans('Zip code'),
                    'attr' => [
                        'placeholder' => $tr->trans('Zip code'),
                    ],
                ]
            )
            ->add(
                'store_city',
                TextType::class,
                [
                    'data' => ConfigQuery::read('store_city'),
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => $tr->trans('City'),
                    'attr' => [
                        'placeholder' => $tr->trans('City'),
                    ],
                ]
            )
            ->add(
                'store_country',
                IntegerType::class,
                [
                    'data' => ConfigQuery::read('store_country'),
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => $tr->trans('Country'),
                    'attr' => [
                        'placeholder' => $tr->trans('Country'),
                    ],
                ]
            )
            ->add(
                'favicon_file',
                FileType::class,
                [
                    'required' => false,
                    'constraints' => [
                        new Image([
                            'mimeTypes' => ['image/png', 'image/x-icon'],
                        ]),
                    ],
                    'label' => $tr->trans('Favicon image'),
                    'label_attr' => [
                        'for' => 'favicon_file',
                        'help' => $tr->trans('Icon of the website. Only PNG and ICO files are allowed.'),
                    ],
                ]
            )
            ->add(
                'logo_file',
                FileType::class,
                [
                    'required' => false,
                    'constraints' => [
                        new Image(),
                    ],
                    'label' => $tr->trans('Store logo'),
                    'label_attr' => [
                        'for' => 'logo_file',
                    ],
                ]
            )
            ->add(
                'banner_file',
                FileType::class,
                [
                    'required' => false,
                    'constraints' => [
                        new Image(),
                    ],
                    'label' => $tr->trans('Banner'),
                    'label_attr' => [
                        'for' => 'banner_file',
                        'help' => $tr->trans('Banner of the website. Used in the e-mails send to the customers.'),
                    ],
                ]
            );
    }

    public function checkEmailList($value, ExecutionContextInterface $context): void
    {
        $list = preg_split('/[,;]/', (string) $value);

        $emailValidator = new Email();

        foreach ($list as $email) {
            $email = trim($email);

            $context->getValidator()->validate($email, $emailValidator);
        }
    }

    public static function getName(): string
    {
        return 'thelia_configuration_store';
    }
}
