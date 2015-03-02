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

namespace Tinymce\Form;

use Cheque\Cheque;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Tinymce\Tinymce;

/**
 * Class ConfigurationForm
 * @package Cheque\Form
 * @author Thelia <info@thelia.net>
 */
class ConfigurationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'editor_height',
                'integer',
                [
                    'data'  => Tinymce::getConfigValue('editor_height', 0),
                    'label' => $this->translator->trans('Height of the editor area, in pixels. Enter 0 for default ', [], Tinymce::MODULE_DOMAIN),
                ]
            )
            ->add(
                'show_menu_bar',
                'checkbox',
                [
                    'data'       =>intval(Tinymce::getConfigValue('show_menu_bar', 0)) != 0,
                    'label'      => $this->translator->trans('Show the TinyMCE menu bar', [], Tinymce::MODULE_DOMAIN),
                ]
            )
            ->add(
                'force_pasting_as_text',
                'checkbox',
                [
                    'data'       => intval(Tinymce::getConfigValue('force_pasting_as_text', 0)) != 0,
                    'label'      => $this->translator->trans('Force pasting as text', [], Tinymce::MODULE_DOMAIN),
                    'label_attr' => [
                        'help' => $this->translator->trans('If checked, all pasted data will be converted as plain text, removing tags and styles.', [], Tinymce::MODULE_DOMAIN)
                    ]
                ]
            )
            ->add(
                'set_images_as_responsive',
                'checkbox',
                [
                    'data'       => intval(Tinymce::getConfigValue('set_images_as_responsive', 1)) != 0,
                    'label'      => $this->translator->trans('Add responsive class to images', [], Tinymce::MODULE_DOMAIN),
                    'label_attr' => [
                        'help' => $this->translator->trans('If checked, the "img-responsive" class is added by default to inserted images', [], Tinymce::MODULE_DOMAIN)
                    ]
                ]
            )
            ->add(
                'custom_css',
                'textarea',
                [
                    'required'   => false,
                    'data'       => Tinymce::getConfigValue('custom_css', '/* Enter here CSS or LESS code */'),
                    'label'      => $this->translator->trans('Custom CSS available in the editor', [], Tinymce::MODULE_DOMAIN),
                    'label_attr' => [
                        'help' => $this->translator->trans('Enter CSS or LESS code. You may also customize the editor.less file in the plugin template directory.', [], Tinymce::MODULE_DOMAIN)
                    ],
                    'attr' => [
                        'rows' => 10,
                        'style' => 'font-family: \'Courier New\', Courier, monospace;'
                    ]
                ]
            )
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return 'timymce_configuration';
    }
}