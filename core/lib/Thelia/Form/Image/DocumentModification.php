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
namespace Thelia\Form\Image;


use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Created by JetBrains PhpStorm.
 * Date: 9/18/13
 * Time: 3:56 PM
 *
 * Form allowing to process a file
 * @todo refactor make all document using propel inheritance and factorise image behaviour into one single clean action
 *
 * @package File
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
abstract class DocumentModification extends BaseForm
{
    /**
     *
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->form attribute :
     *
     * $this->form->add('name', 'text')
     *   ->add('email', 'email', array(
     *           'attr' => array(
     *               'class' => 'field'
     *           ),
     *           'label' => 'email',
     *           'constraints' => array(
     *               new NotBlank()
     *           )
     *       )
     *   )
     *   ->add('age', 'integer');
     *
     * @return null
     */
    protected function buildForm()
    {
        $this->formBuilder->add(
            'file',
            'file',
            array(
                'constraints' => array(),
                'label' => Translator::getInstance()->trans('Replace current document by this file'),
                'label_attr' => array(
                    'for' => 'file'
                )
            )
        );

        $this->formBuilder
            ->add(
                'title',
                'text',
                array(
                    'constraints' => array(
                        new NotBlank()
                    ),
                    'label' => Translator::getInstance()->trans('Title'),
                    'label_attr' => array(
                        'for' => 'title'
                    )
                )
            )
            ->add(
                'description',
                'text',
                array(
                    'constraints' => array(),
                    'label' => Translator::getInstance()->trans('Description'),
                    'label_attr' => array(
                        'for' => 'description'
                    )
                )
            )
            ->add(
                'chapo',
                'text',
                array(
                    'constraints' => array(),
                    'label' => Translator::getInstance()->trans('Chapo'),
                    'label_attr' => array(
                        'for' => 'chapo'
                    )
                )
            )
            ->add(
                'postscriptum',
                'text',
                array(
                    'constraints' => array(),
                    'label' => Translator::getInstance()->trans('Post Scriptum'),
                    'label_attr' => array(
                        'for' => 'postscriptum'
                    )
                )
            )
        ;
    }
}
