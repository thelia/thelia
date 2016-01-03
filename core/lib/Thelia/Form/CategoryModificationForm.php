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
use Thelia\Model\TemplateQuery;

class CategoryModificationForm extends CategoryCreationForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm()
    {
        $this->doBuilForm(
            $this->translator->trans('The category title')
        );

        // Create countries and shipping modules list
        $templateList = [0 => '   '];

        $list = TemplateQuery::create()->find();

        // Get the current edition locale
        $locale = $this->getRequest()->getSession()->getAdminEditionLang()->getLocale();

        /** @var \Thelia\Model\Template $item */
        foreach ($list as $item) {
            $templateList[$item->getId()] = $item->setLocale($locale)->getName();
        }

        asort($templateList);

        $templateList[0] = $this->translator->trans("None");

        $this->formBuilder
            ->add(
                'id',
                'hidden',
                [
                    'constraints' => [ new GreaterThan(array('value' => 0)) ]
                ]
            )
            ->add(
                'default_template_id',
                'choice',
                [
                    'choices'     => $templateList,
                    'label'       => $this->translator->trans('Default product template'),
                    'label_attr'  => [
                        'for'         => 'price_offset_type',
                        'help'        => $this->translator->trans(
                            'Select a default template for new products created in this category'
                        )
                    ],
                    'attr' => [
                    ]
                ]
            )
        ;

        // Add standard description fields, excluding title which is defined in parent class
        $this->addStandardDescFields([ 'title' ]);
    }

    public function getName()
    {
        return "thelia_category_modification";
    }
}
