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

use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotEqualTo;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CountryQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Module;
use Thelia\Module\BaseModule;

/**
 * Allow to build a form Coupon
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponCreationForm extends BaseForm
{
    const COUPON_CREATION_FORM_NAME = 'thelia_coupon_creation';

    /**
     * Build Coupon form
     *
     * @return void
     */
    protected function buildForm()
    {
        // Create countries and shipping modules list
        $countries = [0 => '   '];

        $list = CountryQuery::create()->find();

        /** @var Country $item */
        foreach ($list as $item) {
            $countries[$item->getId()] = $item->getTitle();
        }

        asort($countries);

        $countries[0] = Translator::getInstance()->trans("All countries");

        $modules = [0 => '   '];

        $list = ModuleQuery::create()->filterByActivate(BaseModule::IS_ACTIVATED)->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->find();

        /** @var Module $item */
        foreach ($list as $item) {
            $modules[$item->getId()] = $item->getTitle();
        }

        asort($modules);

        $modules[0] = Translator::getInstance()->trans("All shipping methods");

        $this->formBuilder
            ->add(
                'code',
                'text',
                array(
                    'constraints' => array(
                        new NotBlank()
                    )
                )
            )
            ->add(
                'title',
                'text',
                array(
                    'constraints' => array(
                        new NotBlank()
                    )
                )
            )
            ->add(
                'shortDescription',
                'text'

            )
            ->add(
                'description',
                'textarea'

            )
            ->add(
                'type',
                'text',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new NotEqualTo(
                            array(
                                'value' => -1
                            )
                        )
                    )
                )
            )
            ->add(
                'isEnabled',
                'text',
                array()
            )
            ->add(
                'expirationDate',
                'text',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new Callback(array(
                            "methods" => array(
                                array($this, "checkLocalizedDate"),
                            ),
                        ))
                    )
                )
            )
            ->add(
                'isCumulative',
                'text',
                array()
            )
            ->add(
                'isRemovingPostage',
                'text',
                array()
            )
            ->add(
                'freeShippingForCountries',
                'choice',
                array(
                    'multiple' => true,
                    'choices' => $countries
                )
            )
            ->add(
                'freeShippingForModules',
                'choice',
                array(
                    'multiple' => true,
                    'choices' => $modules
                )
            )
            ->add(
                'isAvailableOnSpecialOffers',
                'text',
                array()
            )
            ->add(
                'maxUsage',
                'text',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(['value' => -1])
                    )
                )
            )
            ->add(
                'perCustomerUsageCount',
                'choice',
                array(
                    'multiple' => false,
                    'required' => true,
                    'choices'  => [
                        1 => Translator::getInstance()->trans('Per customer'),
                        0 => Translator::getInstance()->trans('Overall')
                    ]
                )
            )
            ->add(
                'locale',
                'hidden',
                array(
                    'constraints' => array(
                        new NotBlank()
                    )
                )
            )
            ->add('coupon_specific', 'collection', array(
                    'allow_add'    => true,
                    'allow_delete' => true,
            ))
        ;
    }

    /**
     * Validate a date entered with the default Language date format.
     *
     * @param string                    $value
     * @param ExecutionContextInterface $context
     */
    public function checkLocalizedDate($value, ExecutionContextInterface $context)
    {
        $format = LangQuery::create()->findOneByByDefault(true)->getDateFormat();

        if (false === \DateTime::createFromFormat($format, $value)) {
            $context->addViolation(Translator::getInstance()->trans("Date '%date' is invalid, please enter a valid date using %fmt format", [
                '%fmt' => $format,
                '%date' => $value
            ]));
        }
    }

    /**
     * Get form name
     *
     * @return string
     */
    public function getName()
    {
        return self::COUPON_CREATION_FORM_NAME;
    }
}
