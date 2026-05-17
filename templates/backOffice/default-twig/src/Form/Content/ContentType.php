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

namespace BackOfficeDefaultTwigBundle\Form\Content;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ContentType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Content title'),
            ])
            ->add('default_folder', IntegerType::class, [
                'constraints' => [new NotBlank(), new GreaterThan(0)],
                'label' => $this->translator->trans('Default folder'),
            ])
            ->add('locale', HiddenType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('visible', CheckboxType::class, [
                'required' => false,
                'label' => $this->translator->trans('This content is online'),
            ]);

        if ($options['include_id']) {
            $builder->add('id', HiddenType::class, [
                'constraints' => [new NotBlank(), new GreaterThan(0)],
            ]);
        }

        if ($options['include_description']) {
            $builder
                ->add('chapo', TextareaType::class, ['required' => false, 'label' => $this->translator->trans('Summary')])
                ->add('description', TextareaType::class, ['required' => false, 'label' => $this->translator->trans('Detailed description')])
                ->add('postscriptum', TextareaType::class, ['required' => false, 'label' => $this->translator->trans('Conclusion')]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['include_id' => false, 'include_description' => false, 'csrf_token_id' => 'admin.content'])
            ->setAllowedTypes('include_id', 'bool')
            ->setAllowedTypes('include_description', 'bool');
    }
}
