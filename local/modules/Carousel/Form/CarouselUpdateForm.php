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
                'text',
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
                'text',
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
                'url' . $id,
                'url',
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
                'text',
                [
                    'constraints' => [],
                    'required' => false,
                    'label' => $this->translator->trans('Title'),
                    'label_attr' => [
                        'for' => 'title_field' . $id
                    ],
                    'attr' => [
                        'placeholder' => $this->translator->trans('A descriptive title')
                    ]
                ]
            )->add(
                'chapo' . $id,
                'textarea',
                [
                    'constraints' => [],
                    'required' => false,
                    'label' => $this->translator->trans('Summary'),
                    'label_attr' => [
                        'for' => 'summary_field' . $id,
                        'help' => $this->translator->trans(
                            'A short description, used when a summary or an introduction is required'
                        )
                    ],
                    'attr' => [
                        'rows' => 3,
                        'placeholder' => $this->translator->trans('Short description text')
                    ]
                ]
            )->add(
                'description' . $id,
                'textarea',
                [
                    'constraints' => [],
                    'required' => false,
                    'label' => $this->translator->trans('Detailed description'),
                    'label_attr' => [
                        'for' => 'detailed_description_field' . $id,
                        'help' => $this->translator->trans('The detailed description.')
                    ],
                    'attr' => [
                        'rows' => 5
                    ]
                ]
            )->add(
                'postscriptum' . $id,
                'textarea',
                [
                    'constraints' => [],
                    'required' => false,
                    'label' => $this->translator->trans('Conclusion'),
                    'label_attr' => [
                        'for' => 'conclusion_field' . $id,
                        'help' => $this->translator->trans(
                            'A short text, used when an additional or supplemental information is required.'
                        )
                    ],
                    'attr' => [
                        'placeholder' => $this->translator->trans('Short additional text'),
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