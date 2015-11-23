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
use Symfony\Component\Validator\Constraints\Image;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Class CarouselImageForm
 * @package Carousel\Form
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class CarouselImageForm extends BaseForm
{
    /**
     * @inheritdoc
     */
    protected function buildForm()
    {
        $translator = Translator::getInstance();
        $this->formBuilder
            ->add(
                'file',
                'file',
                [
                    'constraints' => [
                        new Image()
                    ],
                    'label' => $translator->trans('Carousel image', [], Carousel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'file'
                    ]
                ]
            );
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return 'carousel_image';
    }
}