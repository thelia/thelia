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

use Symfony\Component\Validator\Constraints\GreaterThan;


class CountryModificationForm extends CountryCreationForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm()
    {
        parent::buildForm(true);

        $this->formBuilder
            ->add('id', 'hidden', ['constraints' => [new GreaterThan(['value' => 0])]])
            ->add(
                'need_zip_code',
                'checkbox',
                [
                    'required'    => false,
                    'label'       => $this->translator->trans('Addresses for this country need a zip code'),
                    'label_attr' => [
                        'for' => 'need_zip_code',
                    ],
                ]
            )
            ->add(
                'zip_code_format',
                'text',
                [
                    'required'    => false,
                    'constraints' => [],
                    'label' => $this->translator->trans('The zip code format'),
                    'label_attr' => [
                        'help' => $this->translator->trans(
                            'Use a N for a number, L for Letter, C for an iso code for the state.'
                        )
                    ],
                ]
            )
        ;

        // Add standard description fields, excluding title and locale, which a re defined in parent class
        $this->addStandardDescFields(['title', 'locale']);
    }

    public function getName()
    {
        return "thelia_country_modification";
    }
}
