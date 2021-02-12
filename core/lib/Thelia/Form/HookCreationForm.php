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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Translation\Translator;
use Thelia\Model\HookQuery;

/**
 * Class HookCreationForm
 * @package Thelia\Form
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookCreationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("code", TextType::class, [
                "constraints" => [
                    new NotBlank(),
                    new Callback([$this, "checkCodeUnicity"]),
                ],
                "label" => Translator::getInstance()->trans("Hook code"),
                "label_attr" => [
                    "for" => "code",
                ],
            ])
            ->add("locale", HiddenType::class, [
                "constraints" => [
                    new NotBlank(),
                ],
            ])
            ->add("type", ChoiceType::class, [
                "choices" => [
                    Translator::getInstance()->trans("Front Office") => TemplateDefinition::FRONT_OFFICE,
                    Translator::getInstance()->trans("Back Office") => TemplateDefinition::BACK_OFFICE,
                    Translator::getInstance()->trans("email") => TemplateDefinition::EMAIL,
                    Translator::getInstance()->trans("pdf") => TemplateDefinition::PDF,
                ],
                "constraints" => [
                    new NotBlank(),
                ],
                "label" => Translator::getInstance()->trans("Type"),
                "label_attr" => [
                    "for" => "type",
                ],
            ])
            ->add("native", HiddenType::class, [
                "label" => Translator::getInstance()->trans("Native"),
                "label_attr" => [
                    "for" => "native",
                    "help" => Translator::getInstance()->trans("Core hook of Thelia."),
                ],
            ])
            ->add("active", CheckboxType::class, [
                "label" => Translator::getInstance()->trans("Active"),
                "required" => false,
                "label_attr" => [
                    "for" => "active",
                ],
            ])
            ->add("title", TextType::class, [
                "constraints" => [
                    new NotBlank(),
                ],
                "label" => Translator::getInstance()->trans("Hook title"),
                "label_attr" => [
                    "for" => "title",
                ],
            ])
        ;
    }

    public function checkCodeUnicity($code, ExecutionContextInterface $context)
    {
        $type = $context->getRoot()->getData()['type'];

        $query = HookQuery::create()->filterByCode($code)->filterByType($type);

        if ($this->form->has('id')) {
            $query->filterById($this->form->getRoot()->getData()['id'], Criteria::NOT_EQUAL);
        }

        if ($query->count() > 0) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    "A Hook with code %name already exists. Please choose another code.",
                    ['%name' => $code]
                )
            );
        }
    }

    public static function getName()
    {
        return "thelia_hook_creation";
    }
}
