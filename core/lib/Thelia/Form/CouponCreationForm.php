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

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotEqualTo;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CountryQuery;
use Thelia\Model\CouponQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Allow to build a form Coupon.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class CouponCreationForm extends BaseForm
{
    public const COUPON_CREATION_FORM_NAME = 'thelia_coupon_creation';

    /**
     * Build Coupon form.
     */
    protected function buildForm(): void
    {
        // Create countries and shipping modules list
        $countries = [Translator::getInstance()->trans('All countries') => 0];

        $list = CountryQuery::create()->find();

        /** @var \Thelia\Model\Country $item */
        foreach ($list as $item) {
            $countries[$item->getTitle()] = $item->getId();
        }

        asort($countries);

        $modules = [Translator::getInstance()->trans('All shipping methods') => 0];

        $list = ModuleQuery::create()->filterByActivate(BaseModule::IS_ACTIVATED)->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->find();

        /** @var Module $item */
        foreach ($list as $item) {
            $modules[$item->getTitle()] = $item->getId();
        }

        asort($modules);

        $this->formBuilder
            ->add(
                'code',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                        new Callback(
                            [
                                'callback' => [$this, 'checkDuplicateCouponCode'],
                                'groups' => 'creation',
                            ]
                        ),
                        new Callback(
                            [
                                'callback' => [$this, 'checkCouponCodeChangedAndDoesntExists'],
                                'groups' => 'update',
                            ]
                        ),
                    ],
                ]
            )
            ->add(
                'title',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]
            )
            ->add(
                'shortDescription',
                TextType::class
            )
            ->add(
                'description',
                TextareaType::class
            )
            ->add(
                'type',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                        new NotEqualTo(
                            [
                                'value' => -1,
                            ]
                        ),
                    ],
                ]
            )
            ->add(
                'isEnabled',
                TextType::class,
                []
            )
            ->add(
                'startDate',
                TextType::class,
                [
                    'constraints' => [
                        new Callback(
                            [$this, 'checkLocalizedDate']
                        ),
                    ],
                ]
            )
            ->add(
                'expirationDate',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                        new Callback([$this, 'checkLocalizedDate']),
                        new Callback([$this, 'checkConsistencyDates']),
                    ],
                ]
            )
            ->add(
                'isCumulative',
                TextType::class,
                []
            )
            ->add(
                'isRemovingPostage',
                TextType::class,
                []
            )
            ->add(
                'freeShippingForCountries',
                ChoiceType::class,
                [
                    'multiple' => true,
                    'choices' => $countries,
                ]
            )
            ->add(
                'freeShippingForModules',
                ChoiceType::class,
                [
                    'multiple' => true,
                    'choices' => $modules,
                ]
            )
            ->add(
                'isAvailableOnSpecialOffers',
                TextType::class,
                []
            )
            ->add(
                'maxUsage',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                        new GreaterThanOrEqual(['value' => -1]),
                    ],
                ]
            )
            ->add(
                'perCustomerUsageCount',
                ChoiceType::class,
                [
                    'multiple' => false,
                    'required' => true,
                    'choices' => [
                        Translator::getInstance()->trans('Per customer') => 1,
                        Translator::getInstance()->trans('Overall') => 0,
                    ],
                ]
            )
            ->add(
                'locale',
                HiddenType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]
            )
            ->add(
                'coupon_specific',
                // Data will be json encoded on pre-submit because it can contains array or string...
                TextType::class,
                [
                    // Value can be array or string so no validation possible here...
                    'validation_groups' => false,
                ]
            )
        ;

        $this->formBuilder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();

            if (isset($data['coupon_specific'])) {
                $data['coupon_specific'] = json_encode($data['coupon_specific']);
            }

            $event->setData($data);
        }, 256);
    }

    /**
     * Check coupon code unicity.
     */
    public function checkDuplicateCouponCode(string $value, ExecutionContextInterface $context): void
    {
        $exists = CouponQuery::create()->filterByCode($value)->count() > 0;

        if ($exists) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    "The coupon code '%code' already exists. Please choose another coupon code",
                    ['%code' => $value]
                )
            );
        }
    }

    public function checkCouponCodeChangedAndDoesntExists($value, ExecutionContextInterface $context): void
    {
        $data = $context->getRoot()->getData();

        $changed = isset($data['code']) && $data['code'] !== $value;
        $exists = CouponQuery::create()->filterByCode($value)->count() > 0;

        if ($changed && $exists) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    "The coupon code '%code' already exist. Please choose another coupon code",
                    ['%code' => $value]
                )
            );
        }
    }

    /**
     * Validate a date entered with the default Language date format.
     */
    public function checkLocalizedDate(string $value, ExecutionContextInterface $context): void
    {
        $format = LangQuery::create()->findOneByByDefault(true)->getDatetimeFormat();

        if (false === \DateTime::createFromFormat($format, $value)) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    "Date '%date' is invalid, please enter a valid date using %fmt format",
                    [
                        '%fmt' => $format,
                        '%date' => $value,
                    ]
                )
            );
        }
    }

    public function checkConsistencyDates($value, ExecutionContextInterface $context): void
    {
        if (null === $startDate = $this->getForm()->get('startDate')->getData()) {
            return;
        }

        $format = LangQuery::create()->findOneByByDefault(true)->getDatetimeFormat();

        $startDate = \DateTime::createFromFormat($format, $startDate);
        $expirationDate = \DateTime::createFromFormat($format, $value);

        if ($startDate <= $expirationDate) {
            return;
        }

        $context->addViolation(
            Translator::getInstance()->trans('Start date and expiration date are inconsistent')
        );
    }

    /**
     * Get form name.
     *
     * @return string
     */
    public static function getName()
    {
        return self::COUPON_CREATION_FORM_NAME;
    }
}
