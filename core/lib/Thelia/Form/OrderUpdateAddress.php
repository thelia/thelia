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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CountryQuery;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\OrderAddressQuery;

/**
 * Class AddressUpdateForm.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderUpdateAddress extends BaseForm
{
    use AddressCountryValidationTrait;

    protected function buildForm()
    {
        $this->formBuilder
            ->add('id', IntegerType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Callback(
                            [$this, 'verifyId']
                    ),
                ],
                'required' => true,
            ])
            ->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Callback(
                            [$this, 'verifyTitle']
                    ),
                ],
                'label' => Translator::getInstance()->trans('Title'),
                'label_attr' => [
                    'for' => 'title_update',
                ],
            ])
            ->add('firstname', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('Firstname'),
                'label_attr' => [
                    'for' => 'firstname_update',
                ],
            ])
            ->add('lastname', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('Lastname'),
                'label_attr' => [
                    'for' => 'lastname_update',
                ],
            ])
            ->add('address1', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('Street Address'),
                'label_attr' => [
                    'for' => 'address1_update',
                ],
            ])
            ->add('address2', TextType::class, [
                'required' => false,
                'label' => Translator::getInstance()->trans('Additional address'),
                'label_attr' => [
                    'for' => 'address2_update',
                ],
            ])
            ->add('address3', TextType::class, [
                'required' => false,
                'label' => Translator::getInstance()->trans('Additional address'),
                'label_attr' => [
                    'for' => 'address3_update',
                ],
            ])
            ->add('zipcode', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Callback(
                            [$this, 'verifyZipCode']
                        ),
                ],
                'label' => Translator::getInstance()->trans('Zip code'),
                'label_attr' => [
                    'for' => 'zipcode_update',
                ],
            ])
            ->add('city', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('City'),
                'label_attr' => [
                    'for' => 'city_update',
                ],
            ])
            ->add('country', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Callback(
                            [$this, 'verifyCountry']
                    ),
                ],
                'label' => Translator::getInstance()->trans('Country'),
                'label_attr' => [
                    'for' => 'country_update',
                ],
            ])
            ->add('state', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Callback(
                            [$this, 'verifyState']
                        ),
                ],
                'label' => Translator::getInstance()->trans('State *'),
                'label_attr' => [
                    'for' => 'state',
                ],
            ])
            ->add('phone', TextType::class, [
                'required' => false,
                'label' => Translator::getInstance()->trans('Phone'),
                'label_attr' => [
                    'for' => 'phone_update',
                ],
            ])
            ->add('cellphone', TextType::class, [
                'required' => false,
                'label' => Translator::getInstance()->trans('Cellphone'),
                'label_attr' => [
                    'for' => 'cellphone_update',
                ],
            ])
            ->add('company', TextType::class, [
                'required' => false,
                'label' => Translator::getInstance()->trans('Compagny'),
                'label_attr' => [
                    'for' => 'company_update',
                ],
            ])
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName()
    {
        return 'thelia_order_address_update';
    }

    public function verifyId($value, ExecutionContextInterface $context)
    {
        $address = OrderAddressQuery::create()
            ->findPk($value);

        if (null === $address) {
            $context->addViolation(Translator::getInstance()->trans('Order address ID not found'));
        }
    }

    public function verifyTitle($value, ExecutionContextInterface $context)
    {
        $address = CustomerTitleQuery::create()
            ->findPk($value);

        if (null === $address) {
            $context->addViolation(Translator::getInstance()->trans('Title ID not found'));
        }
    }

    public function verifyCountry($value, ExecutionContextInterface $context)
    {
        $address = CountryQuery::create()
            ->findPk($value);

        if (null === $address) {
            $context->addViolation(Translator::getInstance()->trans('Country ID not found'));
        }
    }
}
