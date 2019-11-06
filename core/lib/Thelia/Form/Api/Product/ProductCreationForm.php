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

namespace Thelia\Form\Api\Product;

use Thelia\Core\Translation\Translator;
use Thelia\Form\ProductCreationForm as BaseProductCreationForm;
use Thelia\Form\StandardDescriptionFieldsTrait;

/**
 * Class ProductCreateForm
 * @package Thelia\Form\Api\Product
 * @author manuel raynaud <manu@raynaud.io>
 */
class ProductCreationForm extends BaseProductCreationForm
{
    use StandardDescriptionFieldsTrait;

    /**
     * @inherited
     */
    protected function buildForm()
    {
        $translator = Translator::getInstance();
        BaseProductCreationForm::buildForm();

        $this
            ->formBuilder
            ->add("brand_id", "integer", [
                'required'    => true,
                'label'       => $translator->trans('Brand / Supplier'),
                'label_attr'  => [
                    'for' => 'mode',
                    'help' => $translator->trans("Select the product brand, or supplier."),
                ],
            ]);

        $this->addStandardDescFields(array('title', 'locale'));
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return '';
    }
}
