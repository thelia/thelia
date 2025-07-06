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

use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;

class AdminCreatePassword extends BruteforceForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('password', PasswordType::class, [
                'constraints' => [],
                'label' => $this->translator->trans('Password'),
                'label_attr' => [
                    'for' => 'password',
                ],
                'attr' => [
                    'placeholder' => Translator::getInstance()->trans('Enter the new password'),
                ],
            ])
            ->add('password_confirm', PasswordType::class, [
                'constraints' => [
                    new Callback(
                        $this->verifyPasswordField(...)
                    ),
                ],
                'label' => $this->translator->trans('Password confirmation'),
                'label_attr' => [
                    'for' => 'password_confirmation',
                ],
                'attr' => [
                    'placeholder' => Translator::getInstance()->trans('Enter the new password again'),
                ],
            ])
        ;
    }

    public function verifyPasswordField($value, ExecutionContextInterface $context): void
    {
        $data = $context->getRoot()->getData();

        if ($data['password'] === '' && $data['password_confirm'] === '') {
            $context->addViolation("password can't be empty");
        }

        if ($data['password'] != $data['password_confirm']) {
            $context->addViolation('password confirmation is not the same as password field');
        }

        $minLength = ConfigQuery::getMinimuAdminPasswordLength();

        if (\strlen((string) $data['password']) < $minLength) {
            $context->addViolation(\sprintf('password must be composed of at least %s characters', $minLength));
        }
    }
}
