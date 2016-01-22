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

use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Model\LangQuery;

/**
 * Class ExportForm
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ExportForm extends BaseForm
{
    public function getName()
    {
        return 'thelia_export';
    }

    protected function buildForm()
    {
        $this->formBuilder
            // Todo: use list
            ->add(
                'serializer',
                'text',
                [
                    'required' => true,
                    'label' => $this->translator->trans('File format'),
                    'label_attr' => [
                        'for' => 'serializer'
                    ],
                ]
            )
            // Todo: use list
            ->add(
                'language',
                'integer',
                [
                    'required' => true,
                    'label' => $this->translator->trans('Language'),
                    'label_attr' => [
                        'for' => 'language'
                    ],
                    'constraints' => [
                        new Callback([
                            'methods' => [
                                [$this, 'checkLanguage'],
                            ],
                        ]),
                    ],
                ]
            )
            ->add("do_compress", "checkbox", array(
                "label" => $this->translator->trans("Do compress"),
                "label_attr" => ["for" => "do_compress"],
                "required" => false,
            ))
            // Todo: use list
            ->add(
                'archiver',
                'text',
                [
                    'required' => false,
                    'label' => $this->translator->trans('Archive Format'),
                    'label_attr' => [
                        'for' => 'archiver'
                    ],
                ]
            )
            ->add("images", "checkbox", array(
                "label" => $this->translator->trans("Include images"),
                "label_attr" => ["for" => "with_images"],
                "required" => false,
            ))
            ->add("documents", "checkbox", array(
                "label" => $this->translator->trans("Include documents"),
                "label_attr" => ["for" => "with_documents"],
                "required" => false,
            ))
            ->add("range_date_start", "date", array(
                "label" => $this->translator->trans("Range date Start"),
                "label_attr" => ["for" => "for_range_date_start"],
                "required" => false,
                'years' => range(date('Y'), date('Y') - 5),
                'input' => 'array',
                'widget' => 'choice',
                'empty_value' => array('year' => 'Year', 'month' => 'Month', 'day' => 'Day'),
                'format' => 'yyyy-MM-d',
            ))
            ->add("range_date_end", "date", array(
                "label" => $this->translator->trans("Range date End"),
                "label_attr" => ["for" => "for_range_date_end"],
                "required" => false,
                'years' => range(date('Y'), date('Y') - 5),
                'input' => 'array',
                'widget' => 'choice',
                'empty_value' => array('year' => 'Year', 'month' => 'Month', 'day' => 'Day'),
                'format' => 'yyyy-MM-d',
            ));
    }

    public function checkLanguage($value, ExecutionContextInterface $context)
    {
        if (null === LangQuery::create()->findPk($value)) {
            $context->addViolation(
                $this->translator->trans(
                    "The language \"%id\" doesn't exist",
                    [
                        "%id" => $value
                    ]
                )
            );
        }
    }
}
