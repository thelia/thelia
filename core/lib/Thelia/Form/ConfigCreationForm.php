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

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;

class ConfigCreationForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Callback($this->checkDuplicateName(...)),
                ],
                'label' => Translator::getInstance()->trans('Name *'),
                'label_attr' => [
                    'for' => 'name',
                ],
            ])
            ->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('Purpose *'),
                'label_attr' => [
                    'for' => 'purpose',
                ],
            ])
            ->add('locale', HiddenType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('value', TextType::class, [
                'label' => Translator::getInstance()->trans('Value *'),
                'label_attr' => [
                    'for' => 'value',
                ],
            ])
            ->add('hidden', HiddenType::class, [])
            ->add('secured', HiddenType::class, [
                'label' => Translator::getInstance()->trans('Prevent variable modification or deletion, except for super-admin'),
            ])
        ;
    }

    public static function getName(): string
    {
        return 'thelia_config_creation';
    }

    public function checkDuplicateName($value, ExecutionContextInterface $context): void
    {
        $config = ConfigQuery::create()->findOneByName($value);

        if ($config) {
            $context->addViolation(Translator::getInstance()->trans('A variable with name "%name" already exists.', ['%name' => $value]));
        }
    }
}
