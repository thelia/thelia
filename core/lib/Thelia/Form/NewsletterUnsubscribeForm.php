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
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\NewsletterQuery;

/**
 * Class NewsletterForm.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class NewsletterUnsubscribeForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Email(),
                    new Callback($this->verifyExistingEmail(...)),
                ],
                'label' => Translator::getInstance()->trans('Email address'),
                'label_attr' => [
                    'for' => 'email_newsletter',
                ],
            ])
        ;
    }

    public function verifyExistingEmail($value, ExecutionContextInterface $context): void
    {
        if (null === NewsletterQuery::create()->filterByUnsubscribed(false)->findOneByEmail($value)) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    'The email address "%mail" was not found.',
                    ['%mail' => $value]
                )
            );
        }
    }
}
