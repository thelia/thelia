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
use Thelia\Core\Translation\Translator;

/**
 * A trait to add standard localized description fields to a form.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
trait StandardDescriptionFieldsTrait
{
    /**
     * Add standard description fields + locale tot the form
     *
     * @param array $exclude name of the fields that should not be added to the form
     */
    protected function addStandardDescFields($exclude = array())
    {
        if (! in_array('locale', $exclude)) {
            $this->formBuilder->add(
                'locale',
                'hidden',
                [
                    'constraints' => [ new NotBlank() ],
                    'required'    => true,
                ]
            );
        }

        if (! in_array('title', $exclude)) {
            $this->formBuilder->add(
                'title',
                'text',
                [
                    'constraints' => [ new NotBlank() ],
                    'required'    => true,
                    'label'       => Translator::getInstance()->trans('Title'),
                    'label_attr'  => [
                        'for' => 'title_field',
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans('A descriptive title'),
                    ]
                ]
            );
        }

        if (! in_array('chapo', $exclude)) {
            $this->formBuilder->add(
                'chapo',
                'textarea',
                [
                    'constraints' => [ ],
                    'required'    => false,
                    'label'       => Translator::getInstance()->trans('Summary'),
                    'label_attr'  => [
                        'for'         => 'summary_field',
                        'help'        => Translator::getInstance()->trans('A short description, used when a summary or an introduction is required'),
                    ],
                    'attr' => [
                        'rows'        => 3,
                        'placeholder' => Translator::getInstance()->trans('Short description text'),
                    ]
                ]
            );
        }

        if (! in_array('description', $exclude)) {
            $this->formBuilder->add(
                'description',
                'textarea',
                [
                    'constraints' => [ ],
                    'required'    => false,
                    'label'       => Translator::getInstance()->trans('Detailed description'),
                    'label_attr'  => [
                        'for'  => 'detailed_description_field',
                        'help' => Translator::getInstance()->trans('The detailed description.'),
                    ],
                    'attr' => [
                        'rows' => 10,
                    ]
                ]
            );
        }

        if (! in_array('postscriptum', $exclude)) {
            $this->formBuilder->add(
                'postscriptum',
                'textarea',
                [
                    'constraints' => [ ],
                    'required'    => false,
                    'label'       => Translator::getInstance()->trans('Conclusion'),
                    'label_attr'  => [
                        'for'         => 'conclusion_field',
                        'help'        => Translator::getInstance()->trans('A short text, used when an additional or supplemental information is required.'),
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans('Short additional text'),
                        'rows'        => 3,
                    ]
                ]
            );
        }
    }
}
