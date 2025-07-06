<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Thelia\Form;

use Thelia\Model\Template;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Thelia\Model\TemplateQuery;

class CategoryModificationForm extends CategoryCreationForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm(): void
    {
        $this->doBuilForm(
            $this->translator->trans('The category title')
        );

        // Create countries and shipping modules list
        $templateList = [$this->translator->trans('None') => 0];

        $list = TemplateQuery::create()->find();

        // Get the current edition locale
        $locale = $this->getRequest()->getSession()->getAdminEditionLang()->getLocale();

        /** @var Template $item */
        foreach ($list as $item) {
            $templateList[$item->setLocale($locale)->getName()] = $item->getId();
        }

        asort($templateList);

        $this->formBuilder
            ->add(
                'id',
                HiddenType::class,
                [
                    'constraints' => [new GreaterThan(['value' => 0])],
                ]
            )
            ->add(
                'default_template_id',
                ChoiceType::class,
                [
                    'choices' => $templateList,
                    'label' => $this->translator->trans('Default product template'),
                    'label_attr' => [
                        'for' => 'price_offset_type',
                        'help' => $this->translator->trans(
                            'Select a default template for new products created in this category'
                        ),
                    ],
                    'attr' => [
                    ],
                ]
            )
        ;

        // Add standard description fields, excluding title which is defined in parent class
        $this->addStandardDescFields(['title']);
    }

    public static function getName(): string
    {
        return 'thelia_category_modification';
    }
}
