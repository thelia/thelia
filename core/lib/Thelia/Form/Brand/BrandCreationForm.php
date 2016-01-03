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

use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\Lang;

/**
 * Class BrandCreationForm
 * @package Thelia\Form\Brand
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class BrandCreationForm extends BaseForm
{
    protected function doBuilForm($titleFieldHelpLabel)
    {
        $this->formBuilder->add(
            'title',
            'text',
            [
                'constraints' => [ new NotBlank() ],
                'required'    => true,
                'label'       => Translator::getInstance()->trans('Brand name'),
                'label_attr'  => [
                    'for'         => 'title',
                    'help'        => $titleFieldHelpLabel,
                ],
                'attr' => [
                    'placeholder' => Translator::getInstance()->trans('The brand name or title'),
                ]
            ]
        )
        ->add(
            'locale',
            'hidden',
            [
                'constraints' => [ new NotBlank() ],
                'required'    => true,
            ]
        )
        // Is this brand online ?
        ->add(
            'visible',
            'checkbox',
            [
                'required'    => false,
                'label'       => Translator::getInstance()->trans('This brand is online'),
                'label_attr' => [
                    'for' => 'visible_create',
                ],
                'attr' => [
                    'checked' => 'checked'
                ]
            ]
        );
    }

    protected function buildForm()
    {
        $this->doBuilForm(
            Translator::getInstance()->trans(
                'Enter here the brand name in the default language (%title%)',
                [ '%title%' => Lang::getDefaultLanguage()->getTitle()]
            )
        );
    }

    public function getName()
    {
        return 'thelia_brand_creation';
    }
}
