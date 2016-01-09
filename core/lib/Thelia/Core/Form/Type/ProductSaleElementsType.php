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

namespace Thelia\Core\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Thelia\Core\Form\Type\Field\ProductIdType;
use Thelia\Core\Form\Type\Field\ProductSaleElementsIdType;

/**
 * Class ProductSaleElementsType
 * @package Thelia\Core\Form\Type
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ProductSaleElementsType extends AbstractTheliaType
{
    protected $productIdType;
    protected $pseIdType;

    /**
     * @param ProductIdType             $productIdType
     * @param ProductSaleElementsIdType $pseIdType
     *
     * The types are needed to load the validation groups
     */
    public function __construct(ProductIdType $productIdType, ProductSaleElementsIdType $pseIdType)
    {
        $this->productIdType = $productIdType;

        $this->pseIdType = $pseIdType;
    }

    /**
     * @param OptionsResolver $resolver
     *
     * Always allow cascade validation for types
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            "cascade_validation" => true,
        ]);
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add("reference", "text", array(
                "required" => false,
            ))
            ->add("price", "number", array(
                "required" => false,
                "constraints" => array(
                    new GreaterThanOrEqual(["value" => 0]),
                ),
            ))
            ->add("price_with_tax", "number", array(
                "required" => false,
                "constraints" => array(
                    new GreaterThanOrEqual(["value" => 0]),
                ),
            ))
            ->add("weight", "number", array(
                "required" => false,
                "constraints" => array(
                    new GreaterThanOrEqual(["value" => 0]),
                ),
            ))
            ->add("quantity", "number", array(
                "required" => false,
                "constraints" => array(
                    new GreaterThanOrEqual(["value" => 0]),
                ),
            ))
            ->add("sale_price", "number", array(
                "required" => false,
                "constraints" => array(
                    new GreaterThanOrEqual(["value" => 0]),
                ),
            ))
            ->add("sale_price_with_tax", "number", array(
                "required" => false,
                "constraints" => array(
                    new GreaterThanOrEqual(["value" => 0]),
                ),
            ))
            ->add("ean_code", "text", array(
                "required" => false,
            ))
            ->add("attribute_av", "collection", array(
                "type" => "attribute_av",
                "required" => false,
                "allow_add" => true,
            ))
            ->add("tax_rule_id", "tax_rule_id")
            ->add("currency_id", "currency_id")
            ->add("onsale", "checkbox")
            ->add("isnew", "checkbox")
            ->add("isdefault", "checkbox")
            ->add("use_exchange_rate", "checkbox")

            // Only on create
            ->add("product_id", "product_id", array(
                "constraints" => $this->getConstraints($this->productIdType, "create"),
            ))

            // Only on update
            ->add("id", "product_sale_elements_id", array(
                "constraints" => $this->getConstraints($this->pseIdType, "update"),
            ))
        ;
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return "product_sale_elements";
    }
}
