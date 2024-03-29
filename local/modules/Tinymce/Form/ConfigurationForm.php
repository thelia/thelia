<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tinymce\Form;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Form\BaseForm;
use Tinymce\Tinymce;

/**
 * Class ConfigurationForm.
 *
 * @author Thelia <info@thelia.net>
 */
class ConfigurationForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                'editor_height',
                IntegerType::class,
                [
                    'required' => false,
                    'data' => Tinymce::getConfigValue('editor_height', 0),
                    'label' => $this->translator->trans('Height of the editor area, in pixels. Enter 0 for default ', [], Tinymce::MODULE_DOMAIN),
                ]
            )
            ->add(
                'show_menu_bar',
                CheckboxType::class,
                [
                    'required' => false,
                    'data' => (int) Tinymce::getConfigValue('show_menu_bar', 0) != 0,
                    'label' => $this->translator->trans('Show the TinyMCE menu bar', [], Tinymce::MODULE_DOMAIN),
                ]
            )
            ->add(
                'force_pasting_as_text',
                CheckboxType::class,
                [
                    'required' => false,
                    'data' => (int) Tinymce::getConfigValue('force_pasting_as_text', 0) != 0,
                    'label' => $this->translator->trans('Force pasting as text', [], Tinymce::MODULE_DOMAIN),
                    'label_attr' => [
                        'help' => $this->translator->trans('If checked, all pasted data will be converted as plain text, removing tags and styles.', [], Tinymce::MODULE_DOMAIN),
                    ],
                ]
            )
            ->add(
                'set_images_as_responsive',
                CheckboxType::class,
                [
                    'required' => false,
                    'data' => (int) Tinymce::getConfigValue('set_images_as_responsive', 1) != 0,
                    'label' => $this->translator->trans('Add responsive class to images', [], Tinymce::MODULE_DOMAIN),
                    'label_attr' => [
                        'help' => $this->translator->trans('If checked, the "img-responsive" class is added by default to inserted images', [], Tinymce::MODULE_DOMAIN),
                    ],
                ]
            )
            ->add(
                'custom_css',
                TextareaType::class,
                [
                    'required' => false,
                    'data' => Tinymce::getConfigValue('custom_css', '/* Enter here CSS or LESS code */'),
                    'label' => $this->translator->trans('Custom CSS available in the editor', [], Tinymce::MODULE_DOMAIN),
                    'label_attr' => [
                        'help' => $this->translator->trans('Enter CSS or LESS code. You may also customize the editor.less file in the plugin template directory.', [], Tinymce::MODULE_DOMAIN),
                    ],
                    'attr' => [
                        'rows' => 10,
                        'style' => 'font-family: \'Courier New\', Courier, monospace;',
                    ],
                ]
            )
            ->add(
                'test_zone',
                TextareaType::class,
                [
                    'required' => false,
                    'label' => $this->translator->trans('Sample editor', [], Tinymce::MODULE_DOMAIN),
                    'label_attr' => [
                        'help' => $this->translator->trans('This is a sample text editor, to view actual configuration.', [], Tinymce::MODULE_DOMAIN),
                    ],
                ]
            )->add(
                'available_text_areas',
                TextType::class,
                [
                    'disabled' => true,
                    'required' => false,
                    'label_attr' => [],
                    'data' => Tinymce::getConfigValue('available_text_areas'),
                ]
            );

        foreach ($this->getFieldsKeys() as $key) {
            $this->addConfigField($key);
        }
    }

    public function getFieldsKeys()
    {
        return [
            'product_summary',
            'product_conclusion',
            'brand_summary',
            'brand_conclusion',
            'content_summary',
            'content_conclusion',
            'folder_summary',
            'folder_conclusion',
            'category_summary',
            'category_conclusion',
        ];
    }

    protected function addConfigField($key): void
    {
        $this->formBuilder->add(
            $key,
            CheckboxType::class,
            [
                'label_attr' => [],
                'required' => false,
                'constraints' => [],
                'data' => (int) Tinymce::getConfigValue($key, 0) != 0,
            ]
        );
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName()
    {
        return 'timymce_configuration';
    }
}
