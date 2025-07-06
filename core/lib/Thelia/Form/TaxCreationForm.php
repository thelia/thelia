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

namespace Thelia\Form;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Tax;
use Thelia\TaxEngine\TaxTypeRequirementDefinition;
use Thelia\Type\TypeInterface;

/**
 * Class TaxCreationForm.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class TaxCreationForm extends BaseForm
{
    use StandardDescriptionFieldsTrait;

    public function __construct(
        #[TaggedIterator('thelia.taxType')]private iterable $taxTypeIterator
    )
    {
    }

    protected static $typeList = [];

    protected function buildForm(): void
    {
        $typeList = [];
        $requirementList = [];

        foreach ($this->taxTypeIterator as $taxType) {
            $typeList[$taxType->getTitle()] = Tax::escapeTypeName($taxType::class);
            $requirementList[$taxType::class] = $taxType->getRequirementsDefinition();
        }

        $this->formBuilder
            ->add(
                'locale',
                HiddenType::class,
                [
                    'constraints' => [new NotBlank()],
                ]
            )
            ->add(
                'type',
                ChoiceType::class,
                [
                    'choices' => $typeList,
                    'required' => true,
                    'constraints' => [
                        new Constraints\NotBlank(),
                    ],
                    'label' => Translator::getInstance()->trans('Type'),
                    'label_attr' => ['for' => 'type_field'],
                ]
            )
        ;

        foreach ($requirementList as $name => $requirements) {
            /** @var TaxTypeRequirementDefinition $requirement */
            foreach ($requirements as $requirement) {
                if (!isset(self::$typeList[$requirement->getName()])) {
                    self::$typeList[$requirement->getName()] = $requirement->getType();
                }

                $options = array_merge([
                    'constraints' => [
                        new Constraints\Callback($this->checkRequirementField(...)),
                    ],
                    'attr' => [
                        'tag' => 'requirements',
                        'tax_type' => Tax::escapeTypeName($name),
                    ],
                    'label_attr' => [
                        'type' => $requirement->getName(),
                    ],
                    'label' => Translator::getInstance()->trans($requirement->getTitle()),
                ],
                    $requirement->getType()->getFormOptions()
                );

                $this->formBuilder
                    // Replace the '\' in the class name by hyphens
                    // See TaxController::getRequirements if some changes are made about this.
                    ->add(
                        Tax::escapeTypeName($name).':'.$requirement->getName(),
                        $requirement->getType()->getFormType(),
                        $options
                    );
            }
        }

        $this->addStandardDescFields(['postscriptum', 'chapo', 'locale']);
    }

    public function checkRequirementField($value, ExecutionContextInterface $context): void
    {
        $data = $context->getRoot()->getData();
        $type = $data['type'];

        if (str_contains($context->getPropertyPath(), (string) $type)) {
            // extract requirement type
            if (preg_match('@\:(.+)\]@', $context->getPropertyPath(), $matches)) {
                $requirementType = $matches[1];
                if (isset(self::$typeList[$requirementType])) {
                    /** @var TypeInterface $typeClass */
                    $typeClass = self::$typeList[$requirementType];
                    $typeClass->verifyForm($value, $context);

                    return;
                }
            }

            $context->addViolation(
                Translator::getInstance()->trans(
                    'Impossible to check value `%value` for `%type` type',
                    [
                        '%value' => $value,
                        '%type' => $type,
                    ]
                )
            );
        }
    }

    public static function getName()
    {
        return 'thelia_tax_creation';
    }
}
