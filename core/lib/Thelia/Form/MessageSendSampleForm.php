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
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

class MessageSendSampleForm extends BaseForm
{
    public static function getName(): string
    {
        return 'thelia_message_send_sample';
    }

    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                'recipient_email',
                EmailType::class,
                [
                    'constraints' => [new NotBlank()],
                    'label' => Translator::getInstance()->trans('Send test e-mail to:'),
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans('Recipient e-mail address'),
                    ],
                ],
            );
    }
}
