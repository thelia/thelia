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

namespace Thelia\Form\Brand;

use Symfony\Component\Validator\Constraints\GreaterThan;
use Thelia\Core\Translation\Translator;
use Thelia\Form\StandardDescriptionFieldsTrait;

/**
 * Class BrandModificationForm
 * @package Thelia\Form\Brand
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class BrandModificationForm extends BrandCreationForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm()
    {
        $this->doBuilForm(
            Translator::getInstance()->trans('The brand name or title')
        );

        $this->formBuilder->add(
            'id',
            'hidden',
            [
                'constraints' => [ new GreaterThan(['value' => 0]) ],
                'required'    => true,
            ]
        )
        ->add("logo_image_id", "integer", [
                'constraints' => [ ],
                'required'    => false,
                'label'       => Translator::getInstance()->trans('Select the brand logo'),
                'label_attr'  => [
                    'for' => 'logo_image_id',
                    'help' => Translator::getInstance()->trans("Select the brand logo amongst the brand images"),
                ]
            ])
        ;

        // Add standard description fields, excluding title and locale, which are already defined
        $this->addStandardDescFields(array('title', 'locale'));
    }

    public function getName()
    {
        return "thelia_brand_modification";
    }
}
