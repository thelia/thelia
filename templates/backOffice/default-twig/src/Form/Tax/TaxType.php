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

namespace BackOfficeDefaultTwigBundle\Form\Tax;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxTypeInterface;
use Thelia\Model\Tax;
use Thelia\Type\TypeInterface;

final class TaxType extends AbstractType
{
    public function __construct(
        #[AutowireIterator('thelia.taxType')]
        private readonly iterable $taxTypeIterator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $typeChoices = [];
        $requirementTypeByName = [];
        $requirementFields = [];

        foreach ($this->taxTypeIterator as $taxType) {
            \assert($taxType instanceof TaxTypeInterface);
            $escapedName = Tax::escapeTypeName($taxType::class);
            $typeChoices[$taxType->getTitle()] = $escapedName;

            foreach ($taxType->getRequirementsDefinition() as $requirement) {
                $requirementTypeByName[$requirement->getName()] ??= $requirement->getType();
                $requirementFields[] = [
                    'taxType' => $escapedName,
                    'requirement' => $requirement,
                ];
            }
        }

        $builder
            ->add('locale', HiddenType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('title', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Title'),
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => $this->translator->trans('Detailed description'),
                'attr' => ['rows' => 6],
            ])
            ->add('type', ChoiceType::class, [
                'choices' => $typeChoices,
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Type'),
                'placeholder' => false,
                'attr' => ['data-bo-tax-edit-target' => 'typeSelect'],
            ]);

        if ($options['include_id']) {
            $builder->add('id', HiddenType::class, [
                'constraints' => [new NotBlank()],
            ]);
        }

        $validator = function (mixed $value, ExecutionContextInterface $context) use ($requirementTypeByName): void {
            $this->validateRequirement($value, $context, $requirementTypeByName);
        };

        foreach ($requirementFields as $field) {
            $requirement = $field['requirement'];
            $type = $requirement->getType();

            $options = array_merge([
                'required' => false,
                'mapped' => true,
                'constraints' => [new Callback($validator)],
                'attr' => [
                    'data-bo-tax-edit-target' => 'requirement',
                    'data-tax-type' => $field['taxType'],
                ],
                'label' => $this->translator->trans($requirement->getTitle()),
            ], $type->getFormOptions());

            $builder->add(
                $field['taxType'].':'.$requirement->getName(),
                $type->getFormType(),
                $options,
            );
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'include_id' => false,
                'csrf_token_id' => 'admin.tax',
            ])
            ->setAllowedTypes('include_id', 'bool');
    }

    /**
     * @param array<string, TypeInterface> $requirementTypeByName
     */
    private function validateRequirement(mixed $value, ExecutionContextInterface $context, array $requirementTypeByName): void
    {
        $data = $context->getRoot()->getData();
        $selectedType = \is_array($data) ? ($data['type'] ?? null) : null;
        $path = $context->getPropertyPath();

        if (!\is_string($selectedType) || !str_contains($path, $selectedType)) {
            return;
        }

        if (!preg_match('@:(.+)]@', $path, $matches)) {
            return;
        }

        $requirementType = $requirementTypeByName[$matches[1]] ?? null;
        if (!$requirementType instanceof TypeInterface) {
            $context->addViolation($this->translator->trans('Unknown requirement type "%type"', ['%type' => $matches[1]]));

            return;
        }

        $requirementType->verifyForm($value, $context);
    }
}
