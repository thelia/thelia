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
namespace Thelia\Form;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\ProductNotFoundException;
use Thelia\Form\Exception\StockNotFoundException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Class CartAdd.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CartAdd extends BaseForm
{
    /**
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->formBuilder attribute :.
     *
     * $this->formBuilder->add("name", TextType::class)
     *   ->add("email", EmailType::class, array(
     *           "attr" => array(
     *               "class" => "field"
     *           ),
     *           "label" => "email",
     *           "constraints" => array(
     *               new \Symfony\Component\Validator\Constraints\NotBlank()
     *           )
     *       )
     *   )
     *   ->add('age', IntegerType::class);
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add('product', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Callback($this->checkProduct(...)),
                ],
                'label' => 'product',
                'label_attr' => [
                    'for' => 'cart_product',
                ],
            ])
            ->add('product_sale_elements_id', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Callback($this->checkStockAvailability(...)),
                ],
                'required' => true,
            ])
            ->add('quantity', NumberType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Callback($this->checkStock(...)),
                    new GreaterThanOrEqual([
                        'value' => 0,
                    ]),
                ],
                'label' => Translator::getInstance()->trans('Quantity'),
                'label_attr' => [
                    'for' => 'quantity',
                ],
            ])
            ->add('append', IntegerType::class)
            ->add('newness', IntegerType::class)
        ;
    }

    public function checkProduct($value, ExecutionContextInterface $context): void
    {
        $product = ProductQuery::create()->findPk($value);

        if (null === $product || $product->getVisible() == 0) {
            throw new ProductNotFoundException(sprintf(Translator::getInstance()->trans('this product id does not exists : %d'), $value));
        }
    }

    public function checkStockAvailability($value, ExecutionContextInterface $context): void
    {
        if ($value) {
            $data = $context->getRoot()->getData();

            $productSaleElements = ProductSaleElementsQuery::create()
                ->filterById($value)
                ->filterByProductId($data['product'])
                ->count();

            if ($productSaleElements == 0) {
                throw new StockNotFoundException(sprintf(Translator::getInstance()->trans('This product_sale_elements_id does not exists for this product : %d'), $value));
            }
        }
    }

    public function checkStock($value, ExecutionContextInterface $context): void
    {
        $data = $context->getRoot()->getData();

        if (null === $data['product_sale_elements_id']) {
            $context->buildViolation(Translator::getInstance()->trans('Invalid product_sale_elements'));
        } else {
            $productSaleElements = ProductSaleElementsQuery::create()
                ->filterById($data['product_sale_elements_id'])
                ->filterByProductId($data['product'])
                ->findOne();

            $product = $productSaleElements->getProduct();

            if ($productSaleElements->getQuantity() < $value && $product->getVirtual() === 0 && ConfigQuery::checkAvailableStock()) {
                $context->addViolation(Translator::getInstance()->trans('quantity value is not valid'));
            }
        }
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName(): string
    {
        return 'thelia_cart_add';
    }
}
