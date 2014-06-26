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
     * @inheritdoc
     */
    protected function buildForm()
    {
        $this->formBuilder->add(
            'file',
            'file',
            array(
                'required' => false,
                'constraints' => array(),
                'label' => Translator::getInstance()->trans('Replace current document by this file'),
                'label_attr' => array(
                    'for' => 'file'
                )
            )
        )
        ->add(
            'title',
            'text',
            array(
                'required' => true,
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
            'textarea',
            array(
                'required' => false,
                'constraints' => array(),
                'label' => Translator::getInstance()->trans('Description'),
                'label_attr' => array(
                    'for' => 'description',
                    'rows' => 5
                )
            )
        )
        ->add(
            'chapo',
            'textarea',
            array(
                'required' => false,
                'constraints' => array(),
                'label' => Translator::getInstance()->trans('Chapo'),
                'label_attr' => array(
                    'for' => 'chapo',
                    'rows' => 3
                )
            )
        )
        ->add(
            'postscriptum',
            'textarea',
            array(
                'required' => false,
                'constraints' => array(),
                'label' => Translator::getInstance()->trans('Post Scriptum'),
                'label_attr' => array(
                    'for' => 'postscriptum',
                    'rows' => 3
                )
            )
        )
        ->add(
            "locale",
            "hidden",
            array(
                'required' => true,
                "constraints" => array(
                    new NotBlank()
                ),
                "label_attr" => array(
                    "for" => "locale_create"
                )
            )
        )
        ;
    }
}
