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
use Thelia\Model\Lang;
use Thelia\Model\MessageQuery;

class MessageCreationForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Callback($this->checkDuplicateName(...)),
                ],
                'label' => Translator::getInstance()->trans('Name'),
                'label_attr' => [
                    'for' => 'name',
                    'help' => Translator::getInstance()->trans('This is an identifier that will be used in the code to get this message'),
                ],
                'attr' => [
                    'placeholder' => Translator::getInstance()->trans('Mail template name'),
                ],
            ])
            ->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('Purpose'),
                'label_attr' => [
                    'for' => 'purpose',
                    'help' => Translator::getInstance()->trans(
                        'Enter here the mail template purpose in the default language (%title%)',
                        ['%title%' => Lang::getDefaultLanguage()->getTitle()]
                    ),
                ],
                'attr' => [
                    'placeholder' => Translator::getInstance()->trans('Mail template purpose'),
                ],
            ])
            ->add('locale', HiddenType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('secured', HiddenType::class, [])
        ;
    }

    public static function getName(): string
    {
        return 'thelia_message_creation';
    }

    public function checkDuplicateName($value, ExecutionContextInterface $context): void
    {
        $message = MessageQuery::create()->findOneByName($value);

        if ($message) {
            $context->addViolation(Translator::getInstance()->trans('A message with name "%name" already exists.', ['%name' => $value]));
        }
    }
}
