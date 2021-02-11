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

namespace Cheque\Form;

use Cheque\Cheque;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Class ConfigurationForm
 * @package Cheque\Form
 * @author Thelia <info@thelia.net>
 */
class ConfigurationForm extends BaseForm
{
    protected function trans($str, $params = [])
    {
        return Translator::getInstance()->trans($str, $params, Cheque::MESSAGE_DOMAIN);
    }

    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'payable_to',
                'text',
                [
                    'constraints' => [ new NotBlank() ],
                    'label'       => $this->trans('Cheque is payable to: '),
                    'label_attr' => [
                        'for' => 'payable_to',
                        'help' => $this->trans('The name to which the cheque shoud be payable to.')
                    ],
                    'attr' => [
                        'rows'        => 10,
                        'placeholder' => $this->trans('Pay cheque to')
                    ]
                ]
            )
            ->add(
                'instructions',
                'textarea',
                [
                    'constraints' => [],
                    'required'    => false,
                    'label'       => $this->trans('Cheque instructions'),
                    'label_attr' => [
                        'for' => 'namefield',
                        'help' => $this->trans('Please enter here the payment by cheque instructions')
                    ],
                    'attr' => [
                        'rows'        => 10,
                        'placeholder' => $this->trans('Payment instruction')
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
        return 'cheque_configuration_instructions';
    }
}
