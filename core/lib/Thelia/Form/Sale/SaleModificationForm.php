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

namespace Thelia\Form\Sale;

use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Form\StandardDescriptionFieldsTrait;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Sale;

/**
 * Class SaleModificationForm.phpModificationForm
 * @package Thelia\Form\SaleModificationForm.php
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class SaleModificationForm extends SaleCreationForm
{
    // The date format for the start and end date
    const PHP_DATE_FORMAT = "Y-m-d H:i:s";
    const MOMENT_JS_DATE_FORMAT = "YYYY-MM-DD HH:mm:ss";

    use StandardDescriptionFieldsTrait;

    protected function buildForm()
    {
        $this->doBuildForm(
            Translator::getInstance()->trans('The sale name or title')
        );

        $this->formBuilder->add(
            'id',
            'hidden',
            [
                'constraints' => [ new GreaterThan(['value' => 0]) ],
                'required'    => true,
            ]
        )
        ->add(
            'active',
            'checkbox',
            [
                'constraints' => [ new Type([ 'type' => 'bool']) ],
                'required'    => false,
                'label'       => Translator::getInstance()->trans('Activate this sale'),
                'label_attr'  => [
                    'for'         => 'active',
                ],
                'attr' => [
                ]
            ]
        )
        ->add(
            'display_initial_price',
            'checkbox',
            [
                'constraints' => [ new Type([ 'type' => 'bool']) ],
                'required'    => false,
                'label'       => Translator::getInstance()->trans('Display initial product prices on front-office'),
                'label_attr'  => [
                    'for'         => 'display_initial_price',
                ],
                'attr' => [
                ]
            ]
        )
        ->add(
            'start_date',
            'text',
            [
                'constraints' => [
                    new Callback([
                        "methods" => [
                            [ $this, "checkDate" ],
                        ],
                    ]),
                ],
                'required'    => false,
                'label'       => Translator::getInstance()->trans('Start date of sales'),
                'label_attr'  => [
                    'for'         => 'start_date',
                    'help'        => Translator::getInstance()->trans('The date from which sales are active. Please use %fmt format.', [ '%fmt' => self::MOMENT_JS_DATE_FORMAT]),
                ],
                'attr' => [
                    'data-date-format' => self::MOMENT_JS_DATE_FORMAT,
                ]
            ]
        )
        ->add(
            'end_date',
            'text',
            [
                'constraints' => [
                    new Callback([
                        "methods" => [
                            [ $this, "checkDate" ],
                        ],
                    ]),
                ],
                'required'    => false,
                'label'       => Translator::getInstance()->trans('End date of sales'),
                'label_attr'  => [
                    'for'         => 'end_date',
                    'help'        => Translator::getInstance()->trans('The date after which sales are de-activated. Please use %fmt format.', [ '%fmt' => self::MOMENT_JS_DATE_FORMAT]),
                ],
                'attr' => [
                    'data-date-format' => self::MOMENT_JS_DATE_FORMAT,
                ]
            ]
        )
        ->add(
            'price_offset_type',
            'choice',
            [
                'constraints' => [ new NotBlank() ],
                'choices'     => [
                    Sale::OFFSET_TYPE_AMOUNT     => Translator::getInstance()->trans('Constant amount'),
                    Sale::OFFSET_TYPE_PERCENTAGE => Translator::getInstance()->trans('Percentage'),
                ],
                'required'    => true,
                'label'       => Translator::getInstance()->trans('Discount type'),
                'label_attr'  => [
                    'for'         => 'price_offset_type',
                    'help'        => Translator::getInstance()->trans('Select the discount type that will be applied to original product prices'),
                ],
                'attr' => [
                ]
            ]
        )
        ->add(
            'price_offset',
            'collection',
            [
                'type'         => 'number',
                'required'     => true,
                'allow_add'    => true,
                'allow_delete' => true,
                'options'      => [
                    'constraints' => array(new NotBlank()),
                ],

                'label'        => Translator::getInstance()->trans('Product price offset for each currency'),
                'label_attr'   => [
                    'for' => 'price_offset',
                ],
            ]
        )
        ->add(
            'categories',
            'choice',
            [
                'required'    => true,
                'multiple'    => true,
                'choices'     => $this->getCategoriesIdArray(),
                'label'       => Translator::getInstance()->trans('Product categories'),
                'label_attr'  => [
                    'for'         => 'categories',
                    'help'        => Translator::getInstance()->trans('Select the categories of the products covered by this operation'),
                ],
                'attr' => [
                    'size' => 10,
                ]
            ]
        )
        ->add(
            'products',
            'collection',
            [
                'type'         => 'integer',
                'required'     => false,
                'allow_add'    => true,
                'allow_delete' => true,
                'label'        => Translator::getInstance()->trans('Products'),
                'label_attr'   => [
                    'for'         => 'products',
                    'help'        => Translator::getInstance()->trans('Select the products covered by this operation'),
                ],
                'attr' => [
                ]
            ]
        )
        ->add(
            'product_attributes',
            'collection',
            [
                'type'         => 'text',
                'required'     => false,
                'allow_add'    => true,
                'allow_delete' => true,
                'label'        => Translator::getInstance()->trans('Product attributes'),
                'label_attr'   => [
                    'for'         => 'product_attributes',
                    'help'        => Translator::getInstance()->trans('Select the product attributes included in this operation'),
                ],
                'attr' => [
                ]
            ]
        )
        ;
        // Add standard description fields, excluding title and locale, which are already defined
        $this->addStandardDescFields(array('title', 'locale'));
    }

    /**
     * Validate a date entered with the current edition Language date format.
     *
     * @param string                    $value
     * @param ExecutionContextInterface $context
     */
    public function checkDate($value, ExecutionContextInterface $context)
    {
        $format = self::PHP_DATE_FORMAT;

        if (! empty($value) && false === \DateTime::createFromFormat($format, $value)) {
            $context->addViolation(Translator::getInstance()->trans("Date '%date' is invalid, please enter a valid date using %fmt format", [
                        '%fmt' => self::MOMENT_JS_DATE_FORMAT,
                        '%date' => $value
                    ]));
        }
    }

    public function getName()
    {
        return "thelia_sale_modification";
    }

    private function getCategoriesIdArray()
    {
        $categories = CategoryQuery::create()
            ->select("id")
            ->find()
            ->toArray()
        ;

        $ids = [];

        foreach ($categories as $category) {
            $ids[$category] = $category;
        }

        return $ids;
    }
}
