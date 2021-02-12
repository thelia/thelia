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

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

class AdminLogin extends BruteforceForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add('username', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Length(['min' => 3]),
                ],
                'label' => Translator::getInstance()->trans('Username or e-mail address *'),
                'label_attr' => [
                    'for' => 'username',
                ],
            ])
            ->add('password', PasswordType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('Password *'),
                'label_attr' => [
                    'for' => 'password',
                ],
            ])
            ->add('remember_me', CheckboxType::class, [
                    'value' => 'yes',
                    'label' => Translator::getInstance()->trans('Remember me ?'),
                    'label_attr' => [
                        'for' => 'remember_me',
                    ],
            ])
            ;
    }

    public static function getName()
    {
        return 'thelia_admin_login';
    }
}
