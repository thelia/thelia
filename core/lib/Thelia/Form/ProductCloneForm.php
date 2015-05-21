<?php

namespace Thelia\Form;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Model\ProductQuery;

class ProductCloneForm extends BaseForm
{
    public function getName()
    {
        return 'thelia_product_clone';
    }

    protected function buildForm()
    {
        $this->formBuilder
            ->add('productId', 'integer', [
                'constraints' => [new NotBlank()]
            ])
            ->add('newRef', 'text', [
                'constraints' => [
                    new NotBlank(),
                    new Callback([
                        'methods' => [[$this, 'checkRefDifferent']]
                    ])
                ],
                'label' => $this->translator->trans('Product reference (must be unique)'),
                'label_attr'  => array('for' => 'newRef')
            ])
        ;
    }

    public function checkRefDifferent($value, ExecutionContextInterface $context)
    {
        $originalRef = ProductQuery::create()
            ->filterByRef($value, Criteria::EQUAL)
            ->find();

        if (count($originalRef) !== 0) {
            $context->addViolation($this->translator->trans('This product reference is already assigned to another product.'));
        }
    }
}
