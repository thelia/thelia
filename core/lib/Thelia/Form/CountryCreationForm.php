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

class CountryCreationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add('title', 'text', array(
                'constraints' => array(
                    new NotBlank(),
                ),
                'label' => $this->translator->trans('Country title')
            ))
            ->add('locale', 'hidden', array(
                'constraints' => array(
                    new NotBlank(),
                ),
            ))
            ->add('isocode', 'text', array(
                'constraints' => array(
                    new NotBlank(),
                ),
                'label' => $this->translator->trans('Numerical ISO Code'),
                'label_attr' => array(
                    'help' => $this->translator->trans('Check country iso codes <a href="http://en.wikipedia.org/wiki/ISO_3166-1#Current_codes" target="_blank">here</a>.')
                ),
            ))
            ->add('isoalpha2', 'text', array(
                'constraints' => array(
                    new NotBlank(),
                ),
                'label' => $this->translator->trans('ISO Alpha-2 code'),
                'label_attr' => array(
                    'help' => $this->translator->trans('Check country iso codes <a href="http://en.wikipedia.org/wiki/ISO_3166-1#Current_codes" target="_blank">here</a>.')
                ),
            ))
            ->add('isoalpha3', 'text', array(
                'constraints' => array(
                    new NotBlank(),
                ),
                'label' => $this->translator->trans('ISO Alpha-3 code'),
                'label_attr' => array(
                    'help' => $this->translator->trans('Check country iso codes <a href="http://en.wikipedia.org/wiki/ISO_3166-1#Current_codes" target="_blank">here</a>.')
                ),
            ))
        ;
    }

    public function getName()
    {
        return "thelia_country_creation";
    }
}
