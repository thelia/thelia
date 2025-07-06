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

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Model\LangQuery;

/**
 * Class ExportForm.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ExportForm extends BaseForm
{
    public static function getName(): string
    {
        return 'thelia_export';
    }

    protected function buildForm(): void
    {
        $this->formBuilder
            // Todo: use list
            ->add(
                'serializer',
                TextType::class,
                [
                    'required' => true,
                    'label' => $this->translator->trans('File format'),
                    'label_attr' => [
                        'for' => 'serializer',
                    ],
                ]
            )
            // Todo: use list
            ->add(
                'language',
                IntegerType::class,
                [
                    'required' => true,
                    'label' => $this->translator->trans('Language'),
                    'label_attr' => [
                        'for' => 'language',
                    ],
                    'constraints' => [
                        new Callback(
                            $this->checkLanguage(...)
                        ),
                    ],
                ]
            )
            ->add('do_compress', CheckboxType::class, [
                'label' => $this->translator->trans('Do compress'),
                'label_attr' => ['for' => 'do_compress'],
                'required' => false,
            ])
            // Todo: use list
            ->add(
                'archiver',
                TextType::class,
                [
                    'required' => false,
                    'label' => $this->translator->trans('Archive Format'),
                    'label_attr' => [
                        'for' => 'archiver',
                    ],
                ]
            )
            ->add('images', CheckboxType::class, [
                'label' => $this->translator->trans('Include images'),
                'label_attr' => ['for' => 'with_images'],
                'required' => false,
            ])
            ->add('documents', CheckboxType::class, [
                'label' => $this->translator->trans('Include documents'),
                'label_attr' => ['for' => 'with_documents'],
                'required' => false,
            ])
            ->add('range_date_start', DateType::class, [
                'label' => $this->translator->trans('Range date Start'),
                'label_attr' => ['for' => 'for_range_date_start'],
                'required' => false,
                'years' => range(date('Y'), date('Y') - 5),
                'input' => 'array',
                'widget' => 'choice',
                'format' => 'yyyy-MM-d',
            ])
            ->add('range_date_end', DateType::class, [
                'label' => $this->translator->trans('Range date End'),
                'label_attr' => ['for' => 'for_range_date_end'],
                'required' => false,
                'years' => range(date('Y'), date('Y') - 5),
                'input' => 'array',
                'widget' => 'choice',
                'format' => 'yyyy-MM-d',
            ]);
    }

    public function checkLanguage($value, ExecutionContextInterface $context): void
    {
        if (null === LangQuery::create()->findPk($value)) {
            $context->addViolation(
                $this->translator->trans(
                    "The language \"%id\" doesn't exist",
                    [
                        '%id' => $value,
                    ]
                )
            );
        }
    }
}
