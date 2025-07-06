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

namespace Thelia\Form\OrderStatus;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Form\StandardDescriptionFieldsTrait;
use Thelia\Model\Lang;
use Thelia\Model\OrderStatusQuery;

/**
 * Class OrderStatusCreationForm.
 *
 * @author  Gilles Bourgeat <gbourgeat@openstudio.fr>
 *
 * @since 2.4
 */
class OrderStatusCreationForm extends BaseForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                'title',
                TextType::class,
                [
                    'constraints' => [new NotBlank()],
                    'required' => true,
                    'label' => Translator::getInstance()->trans('Order status name'),
                    'label_attr' => [
                        'for' => 'title',
                        'help' => Translator::getInstance()->trans(
                            'Enter here the order status name in the default language (%title%)',
                            ['%title%' => Lang::getDefaultLanguage()->getTitle()]
                        ),
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans('The order status name or title'),
                    ],
                ]
            )
            ->add(
                'code',
                TextType::class,
                [
                    'constraints' => [
                        new Callback($this->checkUniqueCode(...)),
                        new Callback($this->checkFormatCode(...)),
                        new Callback($this->checkIsRequiredCode(...)),
                    ],
                    'required' => true,
                    'label' => Translator::getInstance()->trans('Order status code'),
                    'label_attr' => [
                        'for' => 'title',
                        'help' => Translator::getInstance()->trans('Enter here the order status code'),
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans('The order status code'),
                    ],
                ]
            )
            ->add(
                'color',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                        new Callback($this->checkColor(...)),
                    ],
                    'required' => false,
                    'label' => Translator::getInstance()->trans('Order status color'),
                    'label_attr' => [
                        'for' => 'title',
                        'help' => Translator::getInstance()->trans('Choice a color for this order status code'),
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans('#000000'),
                    ],
                ]
            );

        $this->addStandardDescFields(['title', 'description', 'chapo', 'postscriptum']);
    }

    public static function getName(): string
    {
        return 'thelia_order_status_creation';
    }

    public function checkColor($value, ExecutionContextInterface $context): void
    {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value)) {
            $context->addViolation(
                Translator::getInstance()->trans('This is not a hexadecimal color.')
            );
        }
    }

    public function checkUniqueCode($value, ExecutionContextInterface $context): void
    {
        $query = OrderStatusQuery::create()
            ->filterByCode($value);

        if ($this->form->has('id')) {
            $query->filterById($this->form->get('id')->getData(), Criteria::NOT_EQUAL);
        }

        if ($query->findOne()) {
            $context->addViolation(
                Translator::getInstance()->trans('This code is already used.')
            );
        }
    }

    public function checkFormatCode($value, ExecutionContextInterface $context): void
    {
        if (!empty($value) && !preg_match('/^\w+$/', (string) $value)) {
            $context->addViolation(
                Translator::getInstance()->trans('This is not a valid code.')
            );
        }
    }

    public function checkIsRequiredCode($value, ExecutionContextInterface $context): void
    {
        if ($this->form->has('id') && null !== ($orderStatus = OrderStatusQuery::create()->findOneById($this->form->get('id')->getData())) && (!$orderStatus->getProtectedStatus() && empty($this->form->get('code')->getData()))) {
            $context->addViolation(
                Translator::getInstance()->trans('This value should not be blank.')
            );
        }
    }
}
