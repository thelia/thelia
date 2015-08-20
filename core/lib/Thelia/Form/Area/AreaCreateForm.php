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

namespace Thelia\Form\Area;

use Thelia\Core\Translation\Translator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;

/**
 * Class AreaCreateForm
 * @package Thelia\Form\Shipping
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AreaCreateForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'name',
                'text',
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => Translator::getInstance()->trans('Shipping zone name'),
                    'label_attr' => [
                        'for' => 'shipping_name'
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans("A name such as Europe or Overseas"),
                    ],
                ]
            )
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return 'thelia_area_creation';
    }
}
