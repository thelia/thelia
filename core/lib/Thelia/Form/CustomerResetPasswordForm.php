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
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;

/**
 * Takes the new password of someone who came back with a password reset link.
 *
 * The account is named by the token, never by the form: a field naming the account
 * would let whoever submits the form choose which one to act on.
 */
class CustomerResetPasswordForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('token', HiddenType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('password', PasswordType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Length(['min' => ConfigQuery::read('password.length', 4)]),
                ],
                'label' => Translator::getInstance()->trans('New Password'),
                'label_attr' => [
                    'for' => 'password',
                ],
                'attr' => [
                    'password_control' => true,
                ],
            ])
            ->add('password_confirm', PasswordType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Length(['min' => ConfigQuery::read('password.length', 4)]),
                    new Callback($this->verifyPasswordField(...)),
                ],
                'label' => Translator::getInstance()->trans('Password confirmation'),
                'label_attr' => [
                    'for' => 'password_confirmation',
                ],
            ]);
    }

    public function verifyPasswordField($value, ExecutionContextInterface $context): void
    {
        $data = $context->getRoot()->getData();

        if ($data['password'] !== $data['password_confirm']) {
            $context->addViolation(Translator::getInstance()->trans('password confirmation is not the same as password field'));
        }
    }

    public static function getName(): string
    {
        return 'thelia_customer_reset_password';
    }
}
