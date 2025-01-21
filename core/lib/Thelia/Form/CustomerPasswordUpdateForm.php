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

use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;

/**
 * Class CustomerPasswordUpdateForm.
 *
 * @author Christophe Laffont <claffont@openstudio.fr>
 */
class CustomerPasswordUpdateForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder

            // Login Information
            ->add('password_old', PasswordType::class, [
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Callback([$this, 'verifyCurrentPasswordField']),
                ],
                'label' => Translator::getInstance()->trans('Current Password'),
                'label_attr' => [
                    'for' => 'password_old',
                ],
            ])
            ->add('password', PasswordType::class, [
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Length(['min' => ConfigQuery::read('password.length', 4)]),
                ],
                'label' => Translator::getInstance()->trans('New Password'),
                'label_attr' => [
                    'for' => 'password',
                ],
                'attr' => [
                    "password_control" => true,
                ]
            ])
            ->add('password_confirm', PasswordType::class, [
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Length(['min' => ConfigQuery::read('password.length', 4)]),
                    new Constraints\Callback([$this, 'verifyPasswordField']),
                ],
                'label' => Translator::getInstance()->trans('Password confirmation'),
                'label_attr' => [
                    'for' => 'password_confirmation',
                ],
            ]);
    }

    public function verifyCurrentPasswordField($value, ExecutionContextInterface $context): void
    {
        /**
         * Retrieve the user recording, because after the login action, the password is deleted in the session.
         */
        $userId = $this->getRequest()->getSession()->getCustomerUser()->getId();
        $user = CustomerQuery::create()->findPk($userId);

        // Check if value of the old password match the password of the current user
        if (!password_verify($value, $user->getPassword())) {
            $context->addViolation(Translator::getInstance()->trans('Your current password does not match.'));
        }
    }

    public function verifyPasswordField($value, ExecutionContextInterface $context): void
    {
        $data = $context->getRoot()->getData();

        if ($data['password'] != $data['password_confirm']) {
            $context->addViolation(Translator::getInstance()->trans('password confirmation is not the same as password field'));
        }
    }

    public static function getName()
    {
        return 'thelia_customer_password_update';
    }
}
