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
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/29/13
 * Time: 3:45 PM.
 *
 * Allow to build a form Install Step 3 Database connection
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class InstallStep3Form extends BaseForm
{
    /**
     * Build Coupon form.
     */
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                'host',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                ],
            )
            ->add(
                'user',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                ],
            )
            ->add(
                'password',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                ],
            )
            ->add(
                'port',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                        new GreaterThan(
                            [
                                'value' => 0,
                            ],
                        ),
                    ],
                ],
            )
            ->add(
                'locale',
                HiddenType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                ],
            );
    }

    /**
     * Get form name.
     */
    public static function getName(): string
    {
        return 'thelia_install_step3';
    }
}
