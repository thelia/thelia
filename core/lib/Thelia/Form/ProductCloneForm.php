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

namespace Thelia\Form;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
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
            ->count();

        if ($originalRef !== 0) {
            $context->addViolation($this->translator->trans('This product reference is already assigned to another product.'));
        }
    }
}
