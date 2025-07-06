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

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

class AdminLostPassword extends BruteforceForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('username_or_email', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Length(['min' => 3]),
                ],
                'label' => Translator::getInstance()->trans('Username or e-mail address *'),
                'label_attr' => [
                    'for' => 'username',
                ],
            ])
        ;
    }
}
