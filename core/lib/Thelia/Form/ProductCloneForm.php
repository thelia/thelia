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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Model\ProductQuery;

class ProductCloneForm extends BaseForm
{
    public static function getName(): string
    {
        return 'thelia_product_clone';
    }

    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('productId', IntegerType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('newRef', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Callback($this->checkRefDifferent(...)),
                ],
                'label' => $this->translator->trans('Product reference (must be unique)'),
                'label_attr' => ['for' => 'newRef'],
            ])
        ;
    }

    public function checkRefDifferent($value, ExecutionContextInterface $context): void
    {
        $originalRef = ProductQuery::create()
            ->filterByRef($value, Criteria::EQUAL)
            ->count();

        if ($originalRef !== 0) {
            $context->addViolation($this->translator->trans('This product reference is already assigned to another product.'));
        }
    }
}
