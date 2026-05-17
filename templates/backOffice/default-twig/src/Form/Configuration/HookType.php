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

namespace BackOfficeDefaultTwigBundle\Form\Configuration;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Template\TemplateDefinition;

final class HookType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Hook code'),
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    $this->translator->trans('Front Office') => TemplateDefinition::FRONT_OFFICE,
                    $this->translator->trans('Back Office') => TemplateDefinition::BACK_OFFICE,
                    $this->translator->trans('email') => TemplateDefinition::EMAIL,
                    $this->translator->trans('pdf') => TemplateDefinition::PDF,
                ],
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Type'),
            ])
            ->add('title', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Hook title'),
            ])
            ->add('active', CheckboxType::class, [
                'required' => false,
                'label' => $this->translator->trans('Active'),
            ])
            ->add('native', HiddenType::class, [
                'data' => '0',
            ])
            ->add('locale', HiddenType::class, [
                'constraints' => [new NotBlank()],
            ]);

        if ($options['include_id']) {
            $builder
                ->add('id', HiddenType::class, [
                    'constraints' => [new NotBlank(), new GreaterThan(0)],
                ])
                ->add('by_module', CheckboxType::class, [
                    'required' => false,
                    'label' => $this->translator->trans('By Module'),
                ])
                ->add('block', CheckboxType::class, [
                    'required' => false,
                    'label' => $this->translator->trans('Hook block'),
                ])
                ->add('chapo', TextareaType::class, [
                    'required' => false,
                    'label' => $this->translator->trans('Short description'),
                ])
                ->add('description', TextareaType::class, [
                    'required' => false,
                    'label' => $this->translator->trans('Description'),
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['include_id' => false, 'csrf_token_id' => 'admin.hook'])
            ->setAllowedTypes('include_id', 'bool');
    }
}
