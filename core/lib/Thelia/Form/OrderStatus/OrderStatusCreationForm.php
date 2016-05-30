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

namespace Thelia\Form\OrderStatus;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Form\StandardDescriptionFieldsTrait;
use Thelia\Model\Lang;
use Thelia\Model\OrderStatusQuery;

/**
 * Class OrderStatusCreationForm
 * @package Thelia\Form\OrderStatus
 * @author  Gilles Bourgeat <gbourgeat@openstudio.fr>
 * @since 2.4
 */
class OrderStatusCreationForm extends BaseForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'title',
                'text',
                [
                    'constraints' => [ new NotBlank() ],
                    'required'    => true,
                    'label'       => Translator::getInstance()->trans('Order status name'),
                    'label_attr'  => [
                        'for'         => 'title',
                        'help'        => Translator::getInstance()->trans(
                            'Enter here the order status name in the default language (%title%)',
                            [ '%title%' => Lang::getDefaultLanguage()->getTitle()]
                        ),
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans('The order status name or title'),
                    ]
                ]
            )
            ->add(
                'code',
                'text',
                [
                    'constraints' => [
                        new Callback([
                            'methods' => [
                                [$this, 'checkUniqueCode'],
                                [$this, 'checkFormatCode'],
                                [$this, 'checkIsRequiredCode']
                            ]
                        ])
                    ],
                    'required'    => true,
                    'label'       => Translator::getInstance()->trans('Order status code'),
                    'label_attr'  => [
                        'for'         => 'title',
                        'help'        => Translator::getInstance()->trans('Enter here the order status code'),
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans('The order status code'),
                    ]
                ]
            )
            ->add(
                'color',
                'text',
                [
                    'constraints' => [
                        new NotBlank(),
                        new Callback([
                            'methods' => [[$this, 'checkColor']]
                        ])
                    ],
                    'required'    => false,
                    'label'       => Translator::getInstance()->trans('Order status color'),
                    'label_attr'  => [
                        'for'         => 'title',
                        'help'        => Translator::getInstance()->trans('Choice a color for this order status code'),
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans('#000000'),
                    ]
                ]
            );

        $this->addStandardDescFields(['title', 'description', 'chapo', 'postscriptum']);
    }

    public function getName()
    {
        return 'thelia_order_status_creation';
    }

    public function checkColor($value, ExecutionContextInterface $context)
    {
        if (!preg_match("/^#[0-9a-fA-F]{6}$/", $value)) {
            $context->addViolation(
                Translator::getInstance()->trans("This is not a hexadecimal color.")
            );
        }
    }

    public function checkUniqueCode($value, ExecutionContextInterface $context)
    {
        $query = OrderStatusQuery::create()
            ->filterByCode($value);

        if ($this->form->has('id')) {
            $query->filterById($this->form->get('id')->getData(), Criteria::NOT_EQUAL);
        }

        if ($query->findOne()) {
            $context->addViolation(
                Translator::getInstance()->trans("This code is already used.")
            );
        }
    }

    public function checkFormatCode($value, ExecutionContextInterface $context)
    {
        if (!empty($value) && !preg_match('/^\w+$/', $value)) {
            $context->addViolation(
                Translator::getInstance()->trans("This is not a valid code.")
            );
        }
    }

    public function checkIsRequiredCode($value, ExecutionContextInterface $context)
    {
        if ($this->form->has('id')) {
            if (null !== $orderStatus = OrderStatusQuery::create()->findOneById($this->form->get('id')->getData())) {
                if (!$orderStatus->getProtectedStatus() && empty($this->form->get('code')->getData())) {
                    $context->addViolation(
                        Translator::getInstance()->trans("This value should not be blank.")
                    );
                }
            }
        }
    }
}
