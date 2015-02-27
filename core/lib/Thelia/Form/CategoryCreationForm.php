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

use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Model\Lang;

class CategoryCreationForm extends BaseForm
{
    protected function doBuilForm($titleHelpText)
    {
        $this->formBuilder
            ->add(
                'title',
                'text',
                [
                    'constraints' => [ new NotBlank() ],
                    'label' => $this->translator->trans('Category title'),
                    'label_attr' => [
                        'help' => $titleHelpText
                    ]
                ]
            )
            ->add(
                'parent',
                'integer',
                [
                    'label' => $this->translator->trans('Parent category'),
                    'constraints' => [ new NotBlank() ],
                    'label_attr' => [
                        'help' => $this->translator->trans('Select the parent category of this category.'),
                    ]
                ]
            )
            ->add(
                'locale',
                'hidden',
                [
                    'constraints' =>  [ new NotBlank() ],
                ]
            )
            ->add(
                'visible',
                'integer', // Should be checkbox, but this is not API compatible, see #1199
                [
                    'required' => false,
                    'label' => $this->translator->trans('This category is online')
                ]
            )
        ;
    }

    protected function buildForm()
    {
        $this->doBuilForm(
            $this->translator->trans(
                'Enter here the category title in the default language (%title%)',
                [ '%title%' => Lang::getDefaultLanguage()->getTitle()]
            )
        );
    }

    public function getName()
    {
        return "thelia_category_creation";
    }
}
