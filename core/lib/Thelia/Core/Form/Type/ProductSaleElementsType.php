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

namespace Thelia\Core\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Thelia\Core\Form\Type\Field\AttributeAvIdType;
use Thelia\Core\Form\Type\Field\CurrencyIdType;
use Thelia\Core\Form\Type\Field\ProductIdType;
use Thelia\Core\Form\Type\Field\ProductSaleElementsIdType;
use Thelia\Core\Form\Type\Field\TaxRuleIdType;

/**
 * Class ProductSaleElementsType.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ProductSaleElementsType extends AbstractTheliaType
{
    /**
     * @param ProductSaleElementsIdType $pseIdType
     *
     * The types are needed to load the validation groups
     */
    public function __construct(protected ProductIdType $productIdType, protected ProductSaleElementsIdType $pseIdType)
    {
    }

    /**
     * @param OptionsResolver $resolver
     *
     * Always allow cascade validation for types
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'cascade_validation' => true,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reference', TextType::class, [
                'required' => false,
            ])
            ->add('price', NumberType::class, [
                'required' => false,
                'constraints' => [
                    new GreaterThanOrEqual(['value' => 0]),
                ],
            ])
            ->add('price_with_tax', NumberType::class, [
                'required' => false,
                'constraints' => [
                    new GreaterThanOrEqual(['value' => 0]),
                ],
            ])
            ->add('weight', NumberType::class, [
                'required' => false,
                'constraints' => [
                    new GreaterThanOrEqual(['value' => 0]),
                ],
            ])
            ->add('quantity', NumberType::class, [
                'required' => false,
                'constraints' => [
                    new GreaterThanOrEqual(['value' => 0]),
                ],
            ])
            ->add('sale_price', NumberType::class, [
                'required' => false,
                'constraints' => [
                    new GreaterThanOrEqual(['value' => 0]),
                ],
            ])
            ->add('sale_price_with_tax', NumberType::class, [
                'required' => false,
                'constraints' => [
                    new GreaterThanOrEqual(['value' => 0]),
                ],
            ])
            ->add('ean_code', TextType::class, [
                'required' => false,
            ])
            ->add('attribute_av', CollectionType::class, [
                'type' => AttributeAvIdType::class,
                'required' => false,
                'allow_add' => true,
            ])
            ->add('tax_rule_id', TaxRuleIdType::class)
            ->add('currency_id', CurrencyIdType::class)
            ->add('onsale', CheckboxType::class)
            ->add('isnew', CheckboxType::class)
            ->add('isdefault', CheckboxType::class)
            ->add('use_exchange_rate', CheckboxType::class)

            // Only on create
            ->add('product_id', ProductIdType::class, [
                'constraints' => $this->getConstraints($this->productIdType, 'create'),
            ])

            // Only on update
            ->add('id', ProductSaleElementsIdType::class, [
                'constraints' => $this->getConstraints($this->pseIdType, 'update'),
            ])
        ;
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName(): string
    {
        return 'product_sale_elements';
    }
}
