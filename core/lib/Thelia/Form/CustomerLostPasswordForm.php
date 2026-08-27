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

use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

/**
 * Class CustomerLostPasswordForm.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CustomerLostPasswordForm extends FirewallForm
{
    /**
     * The address is only checked for shape here.
     *
     * Telling the visitor that an address has no account would answer, to anyone who
     * asks, whether a given person is a customer of this shop — so a well-formed
     * address is always accepted, and the shop replies the same way in both cases.
     */
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Email(),
                ],
                'label' => Translator::getInstance()->trans('Please enter your email address'),
                'label_attr' => [
                    'for' => 'forgot-email',
                ],
            ]);
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName(): string
    {
        return 'thelia_customer_lost_password';
    }
}
