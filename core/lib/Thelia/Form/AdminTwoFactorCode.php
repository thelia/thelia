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

class AdminTwoFactorCode extends BruteforceForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('code', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 32),
                ],
                'label' => Translator::getInstance()->trans('Authentication code *'),
                'label_attr' => [
                    'for' => 'code',
                ],
                'attr' => [
                    'autocomplete' => 'one-time-code',
                    'inputmode' => 'numeric',
                ],
            ]);
    }

    public static function getName(): string
    {
        return 'thelia_admin_two_factor_code';
    }
}
