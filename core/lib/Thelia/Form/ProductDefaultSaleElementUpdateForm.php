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
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Currency;

class ProductDefaultSaleElementUpdateForm extends ProductSaleElementUpdateForm
{
    protected function buildForm()
    {
        $this->formBuilder
        ->add("product_id", IntegerType::class, [
                "label"       => Translator::getInstance()->trans("Product ID *"),
                "label_attr"  => ["for" => "product_id_field"],
                "constraints" => [new GreaterThan(['value' => 0])],
        ])
        ->add("product_sale_element_id", IntegerType::class, [
                "label"       => Translator::getInstance()->trans("Product sale element ID *"),
                "label_attr"  => ["for" => "product_sale_element_id_field"],
        ])
        ->add("reference", TextType::class, [
                "label"      => Translator::getInstance()->trans("Reference *"),
                "label_attr" => ["for" => "reference_field"],
        ])
        ->add("price", NumberType::class, [
                "constraints" => [new NotBlank()],
                "label"      => Translator::getInstance()->trans("Product price excluding taxes *"),
                "label_attr" => ["for" => "price_field"],
        ])
        ->add("price_with_tax", NumberType::class, [
                "label"      => Translator::getInstance()->trans("Product price including taxes"),
                "label_attr" => ["for" => "price_with_tax_field"],
        ])
        ->add("currency", IntegerType::class, [
                "constraints" => [new NotBlank()],
                "label"      => Translator::getInstance()->trans("Price currency *"),
                "label_attr" => ["for" => "currency_field"],
        ])
        ->add("tax_rule", IntegerType::class, [
                "constraints" => [new NotBlank()],
                "label"      => Translator::getInstance()->trans("Tax rule for this product *"),
                "label_attr" => ["for" => "tax_rule_field"],
        ])
        ->add("weight", NumberType::class, [
                "label"      => Translator::getInstance()->trans("Weight"),
                "label_attr" => ["for" => "weight_field"],
        ])
        ->add("quantity", NumberType::class, [
                "constraints" => [new NotBlank()],
                "label"      => Translator::getInstance()->trans("Available quantity *"),
                "label_attr" => ["for" => "quantity_field"],
        ])
        ->add("sale_price", NumberType::class, [
                "label"      => Translator::getInstance()->trans("Sale price excluding taxes"),
                "label_attr" => ["for" => "price_with_tax_field"],
        ])
        ->add("sale_price_with_tax", NumberType::class, [
                "label"      => Translator::getInstance()->trans("Sale price including taxes"),
                "label_attr" => ["for" => "sale_price_with_tax_field"],
        ])
        ->add("onsale", IntegerType::class, [
                "label"      => Translator::getInstance()->trans("This product is on sale"),
                "label_attr" => ["for" => "onsale_field"],
        ])
        ->add("isnew", IntegerType::class, [
                "label"      => Translator::getInstance()->trans("Advertise this product as new"),
                "label_attr" => ["for" => "isnew_field"],
        ])
        ->add("isdefault", IntegerType::class, [
                "label"      => Translator::getInstance()->trans("Is it the default product sale element ?"),
                "label_attr" => ["for" => "isdefault_field"],
        ])
        ->add("ean_code", TextType::class, [
                "label"      => Translator::getInstance()->trans("EAN Code"),
                "label_attr" => ["for" => "ean_code_field"],
        ])
        ->add("use_exchange_rate", IntegerType::class, [
                "label"      => Translator::getInstance()->trans("Apply exchange rates on price in %sym", ["%sym" => Currency::getDefaultCurrency()->getSymbol()]),
                "label_attr" => ["for" => "use_exchange_rate_field"],
        ])
        ;
    }

    public function getName()
    {
        return "thelia_product_default_sale_element_update_form";
    }
}
