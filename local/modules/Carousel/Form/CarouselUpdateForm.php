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

namespace Carousel\Form;

use Carousel\Carousel;
use Carousel\Model\CarouselQuery;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Thelia\Form\BaseForm;

/**
 * Class CarouselUpdateForm
 * @package Carousel\Form
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class CarouselUpdateForm extends BaseForm
{
    /**
     * @inheritdoc
     */
    protected function buildForm()
    {
        $formBuilder = $this->formBuilder;

        $carousels = CarouselQuery::create()->orderByPosition()->find();

        /** @var \Carousel\Model\Carousel $carousel */
        foreach ($carousels as $carousel) {
            $id = $carousel->getId();

            $formBuilder->add(
                'position' . $id,
                TextType::class,
                [
                    'label' => $this->translator->trans('Image position in carousel', [], Carousel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'position' . $id
                    ],
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->translator->trans(
                            'Image position in carousel',
                            [],
                            Carousel::DOMAIN_NAME
                        )
                    ]
                ]
            )->add(
                'alt' . $id,
                TextType::class,
                [
                    'label' => $this->translator->trans('Alternative image text', [], Carousel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'alt' . $id
                    ],
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->translator->trans(
                            'Displayed when image is not visible',
                            [],
                            Carousel::DOMAIN_NAME
                        )
                    ]
                ]
            )->add(
                'group' . $id,
                TextType::class,
                [
                    'label' => $this->translator->trans('Group image', [], Carousel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'group' . $id
                    ],
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->translator->trans(
                            'Group of images',
                            [],
                            Carousel::DOMAIN_NAME
                        )
                    ]
                ]
            )->add(
                'url' . $id,
                UrlType::class,
                [
                    'label' => $this->translator->trans('Image URL', [], Carousel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'url' . $id
                    ],
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->translator->trans(
                            'Please enter a valid URL',
                            [],
                            Carousel::DOMAIN_NAME
                        )
                    ]
                ]
            )->add(
                'title' . $id,
                TextType::class,
                [
                    'constraints' => [],
                    'required' => false,
                    'label' => $this->translator->trans('Title', [], Carousel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'title_field' . $id
                    ],
                    'attr' => [
                        'placeholder' => $this->translator->trans('A descriptive title', [], Carousel::DOMAIN_NAME)
                    ]
                ]
            )->add(
                'chapo' . $id,
                TextareaType::class,
                [
                    'constraints' => [],
                    'required' => false,
                    'label' => $this->translator->trans('Summary', [], Carousel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'summary_field' . $id,
                        'help' => $this->translator->trans(
                            'A short description, used when a summary or an introduction is required',
                            [],
                            Carousel::DOMAIN_NAME
                        )
                    ],
                    'attr' => [
                        'rows' => 3,
                        'placeholder' => $this->translator->trans('Short description text', [], Carousel::DOMAIN_NAME)
                    ]
                ]
            )->add(
                'description' . $id,
                TextareaType::class,
                [
                    'constraints' => [],
                    'required' => false,
                    'label' => $this->translator->trans('Detailed description', [], Carousel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'detailed_description_field' . $id,
                        'help' => $this->translator->trans('The detailed description.', [], Carousel::DOMAIN_NAME)
                    ],
                    'attr' => [
                        'rows' => 5
                    ]
                ]
            )->add(
                'disable' . $id,
                CheckboxType::class,
                [
                    'required' => false,
                    'label' => $this->translator->trans('Disable image', [], Carousel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'enable' . $id,
                    ],
                ]
            )->add(
                'limited' . $id,
                CheckboxType::class,
                [
                    'required' => false,
                    'label' => $this->translator->trans('Limited', [], Carousel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'limited' . $id,
                    ],
                ]
            )->add(
                'start_date' . $id,
                DateTimeType::class,
                [
                    'label' => $this->translator->trans('Start date', [], Carousel::DOMAIN_NAME),
                    'widget' => "single_text",
                    'required' => false,
                ]
            )->add(
                'end_date' . $id,
                DateTimeType::class,
                [
                    'label' => $this->translator->trans('End date', [], Carousel::DOMAIN_NAME),
                    'widget' => "single_text",
                    'required' => false,
                ]
            )->add(
                'postscriptum' . $id,
                TextareaType::class,
                [
                    'constraints' => [],
                    'required' => false,
                    'label' => $this->translator->trans('Conclusion', [], Carousel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'conclusion_field' . $id,
                        'help' => $this->translator->trans(
                            'A short text, used when an additional or supplemental information is required.',
                            [],
                            Carousel::DOMAIN_NAME
                        )
                    ],
                    'attr' => [
                        'placeholder' => $this->translator->trans('Short additional text', [], Carousel::DOMAIN_NAME),
                        'rows' => 3,
                    ]
                ]
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return "carousel_update";
    }
}
