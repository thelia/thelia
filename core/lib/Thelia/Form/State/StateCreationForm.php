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

namespace Thelia\Form\State;

use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;

/**
 * Class StateCreationForm
 * @package Thelia\Form
 * @author Julien Chanséaume <julien@thelia.net>
 */
class StateCreationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'title',
                'text',
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => $this->translator->trans('State title')
                ]
            )
            ->add("country_id", "country_id", array(
                "constraints" => array(
                    new NotBlank(),
                ),
                "label" => $this->translator->trans("Country"),
                "label_attr" => array(
                    "for" => "country",
                ),
            ))
            ->add(
                'locale',
                'hidden',
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]
            )
            ->add(
                'visible',
                'checkbox',
                [
                    'required' => false,
                    'label' => $this->translator->trans('This state is online'),
                    'label_attr' => [
                        'for' => 'visible_create',
                    ]
                ]
            )
            ->add(
                'isocode',
                'text',
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => $this->translator->trans('ISO Code'),
                    'label_attr' => [
                        'help' => $this->translator->trans('Iso code for states. It depends of the country.')
                    ],
                ]
            )
        ;
    }

    public function getName()
    {
        return "thelia_state_creation";
    }
}
