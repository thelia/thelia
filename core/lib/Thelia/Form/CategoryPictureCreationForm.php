<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Form;

use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

class CategoryPictureCreationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
//            ->add('alt')
            ->add('file', 'file', array(
                'constraints' => array(
                    new NotBlank(),
                    new Image(
                        array(
                            'minWidth' => 200,
                            'maxWidth' => 400,
                            'minHeight' => 200,
                            'maxHeight' => 400,
                        )
                    )
                )))
//            ->add('category_id', 'model', array(
//                    'disabled' => false,
//                    'class' => 'Thelia\Model\ProductImage'
//                ))
//            ->add('position', 'integer', array(
//                'constraints' => array(
//                    new NotBlank()
//                )
//            ))
            ->add('title', 'text', array(
                'constraints' => array(
//                    new NotBlank()
                ),
//                'label' => Translator::getInstance()->trans('Category picture title *'),
//                'label_attr' => array(
//                    'for' => 'title'
//                )
            ))
//            ->add('description', 'text', array(
//                    'constraints' => array(
//                        new NotBlank()
//                    ),
//                    'label' => Translator::getInstance()->trans('Category picture description *'),
//                    'label_attr' => array(
//                        'for' => 'description'
//                    )
//                ))
//            ->add('chapo', 'text', array(
//                    'constraints' => array(
//                        new NotBlank()
//                    ),
//                    'label' => Translator::getInstance()->trans('Category picture chapo *'),
//                    'label_attr' => array(
//                        'for' => 'chapo'
//                    )
//                ))
//            ->add('postscriptum', 'text', array(
//                    'constraints' => array(
//                        new NotBlank()
//                    ),
//                    'label' => Translator::getInstance()->trans('Category picture postscriptum *'),
//                    'label_attr' => array(
//                        'for' => 'postscriptum'
//                    )
//                ))

        ;
    }

    public function getName()
    {
        return 'thelia_category_picture_creation';
    }
}
