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
use Thelia\Model\ConfigQuery;

class ConfigStoreForm extends BaseForm
{
    protected function buildForm()
    {
        $tr = Translator::getInstance();

        $this->formBuilder
            ->add(
                'store_name',
                'text',
                [
                    'data'        => ConfigQuery::getStoreName(),
                    'constraints' => [new Constraints\NotBlank()],
                    'label'       => $tr->trans('Store name'),
                    'attr'        => [
                        'placeholder' => $tr->trans('Used in your store front'),
                    ]
                ]
            )
            ->add(
                'store_description',
                'text',
                [
                    'data'     => ConfigQuery::getStoreDescription(),
                    'required' => false,
                    'label'    => $tr->trans('Store description'),
                    'attr'     => [
                        'placeholder' => $tr->trans('Used in your store front'),
                    ]
                ]
            )
            ->add(
                'store_email',
                'text',
                [
                    'data'        => ConfigQuery::getStoreEmail(),
                    'constraints' => [
                        new Constraints\NotBlank(),
                        new Constraints\Email(),
                    ],
                    'label'       => $tr->trans('Store email address'),
                    'attr'        => [
                        'placeholder' => $tr->trans('Contact and sender email address'),
                    ],
                    'label_attr'  => [
                        'help' => $tr->trans('This is the contact email address, and the sender email of all e-mails sent by your store.'),
                    ]
                ]
            )
            ->add(
                'store_notification_emails',
                'text',
                [
                    'data'        => ConfigQuery::read('store_notification_emails'),
                    'constraints' => [
                        new Constraints\NotBlank(),
                        new Constraints\Callback([
                            'methods' => [
                                [$this, 'checkEmailList'],
                            ],
                        ]),
                    ],
                    'label'       => $tr->trans('Email addresses of notification recipients'),
                    'attr'        => [
                        'placeholder' => $tr->trans('A comma separated list of email addresses'),
                    ],
                    'label_attr'  => [
                        'help' => $tr->trans('This is a comma separated list of email addresses where store notifications (such as order placed) are sent.'),
                    ]
                ]
            )
            ->add(
                'store_business_id',
                'text',
                [
                    'data'     => ConfigQuery::read('store_business_id'),
                    'label'    => $tr->trans('Business ID'),
                    'required' => false,
                    'attr'     => [
                        'placeholder' => $tr->trans('Store Business Identification Number (SIRET, etc).'),
                    ]
                ]
            )
            ->add(
                'store_phone',
                'text',
                [
                    'data'     => ConfigQuery::read('store_phone'),
                    'label'    => $tr->trans('Phone'),
                    'required' => false,
                    'attr'     => [
                        'placeholder' => $tr->trans('The store phone number.'),
                    ]
                ]
            )
            ->add(
                'store_fax',
                'text',
                [
                    'data'     => ConfigQuery::read('store_fax'),
                    'label'    => $tr->trans('Fax'),
                    'required' => false,
                    'attr'     => [
                        'placeholder' => $tr->trans('The store fax number.'),
                    ]
                ]
            )
            ->add(
                'store_address1',
                'text',
                [
                    'data'        => ConfigQuery::read('store_address1'),
                    'constraints' => [
                        new Constraints\NotBlank(),
                    ],
                    'label'       => $tr->trans('Street Address'),
                    'attr'        => [
                        'placeholder' => $tr->trans('Address.'),
                    ]
                ]
            )
            ->add(
                'store_address2',
                'text',
                [
                    'data'     => ConfigQuery::read('store_address2'),
                    'required' => false,
                    'attr'     => [
                        'placeholder' => $tr->trans('Additional address information'),
                    ]
                ]
            )
            ->add(
                'store_address3',
                'text',
                [
                    'data'     => ConfigQuery::read('store_address3'),
                    'required' => false,
                    'attr'     => [
                        'placeholder' => $tr->trans('Additional address information'),
                    ]
                ]
            )
            ->add(
                'store_zipcode',
                'text',
                [
                    'data'        => ConfigQuery::read('store_zipcode'),
                    'constraints' => [
                        new Constraints\NotBlank(),
                    ],
                    'label'       => $tr->trans('Zip code'),
                    'attr'        => [
                        'placeholder' => $tr->trans('Zip code'),
                    ]
                ]
            )
            ->add(
                'store_city',
                'text',
                [
                    'data'        => ConfigQuery::read('store_city'),
                    'constraints' => [
                        new Constraints\NotBlank(),
                    ],
                    'label'       => $tr->trans('City'),
                    'attr'        => [
                        'placeholder' => $tr->trans('City'),
                    ]
                ]
            )
            ->add(
                'store_country',
                'integer',
                [
                    'data'        => ConfigQuery::read('store_country'),
                    'constraints' => [
                        new Constraints\NotBlank(),
                    ],
                    'label'       => $tr->trans('Country'),
                    'attr'        => [
                        'placeholder' => $tr->trans('Country'),
                    ]
                ]
            );
    }

    public function checkEmailList($value, ExecutionContextInterface $context)
    {
        $list = preg_split('/[,;]/', $value);

        $emailValidator = new Constraints\Email();

        foreach ($list as $email) {
            $email = trim($email);

            $context->getValidator()->validate($email, $emailValidator);
        }
    }

    public function getName()
    {
        return 'thelia_configuration_store';
    }
}
