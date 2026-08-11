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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ProductQuery;

class ProductModificationForm extends ProductCreationForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm(): void
    {
        parent::doBuildForm(true);

        $this->formBuilder
            ->add('id', IntegerType::class, [
                    'label' => Translator::getInstance()->trans('Prodcut ID *'),
                    'label_attr' => ['for' => 'product_id_field'],
                    'constraints' => [new GreaterThan(['value' => 0])],
            ])
            ->add('template_id', IntegerType::class, [
                    'label' => Translator::getInstance()->trans('Product template'),
                    'label_attr' => ['for' => 'product_template_field'],
            ])
            ->add('brand_id', IntegerType::class, [
                'constraints' => [new NotBlank()],
                'required' => true,
                'label' => Translator::getInstance()->trans('Brand / Supplier'),
                'label_attr' => [
                    'for' => 'mode',
                    'help' => Translator::getInstance()->trans('Select the product brand, or supplier.'),
                ],
            ])
            ->add('virtual_document_id', IntegerType::class, [
                'label' => Translator::getInstance()->trans('Virtual document'),
                'label_attr' => [
                    'for' => 'virtual_document_id_field',
                    'help' => Translator::getInstance()->trans('The file your customer will download once the order is paid. Only the documents of this product which are not visible can be selected, so that they cannot be downloaded for free. Leave empty if this virtual product has nothing to download.'),
                ],
            ])
        ;

        // Add standard description fields, excluding title and locale, which a re defined in parent class
        $this->addStandardDescFields(['title', 'locale']);
    }

    public function checkDuplicateRef($value, ExecutionContextInterface $context): void
    {
        $data = $context->getRoot()->getData();

        $count = ProductQuery::create()
                ->filterById($data['id'], Criteria::NOT_EQUAL)
                ->filterByRef($value)->count();

        if ($count > 0) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    'A product with reference %ref already exists. Please choose another reference.',
                    ['%ref' => $value]
                )
            );
        }
    }

    public static function getName()
    {
        return 'thelia_product_modification';
    }
}
