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

namespace Thelia\Test;

use Thelia\Mailer\MailerFactory;
use Thelia\Model\Customer;

/**
 * A mailer factory that keeps what it was asked to send instead of sending it.
 *
 * Lets a test assert on the message code and on the parameters the calling code chose,
 * which is what that code is responsible for — the wording around them belongs to the
 * email template, and a shop is free to replace it.
 */
class RecordingMailerFactory extends MailerFactory
{
    /** @var list<array{code: string, customer: Customer, parameters: array<string, mixed>}> */
    public array $customerMessages = [];

    public function sendEmailToCustomer(string $messageCode, Customer $customer, array $messageParameters = []): void
    {
        $this->customerMessages[] = [
            'code' => $messageCode,
            'customer' => $customer,
            'parameters' => $messageParameters,
        ];
    }

    /**
     * @return list<array<string, mixed>> the parameters of every message sent with the given code
     */
    public function parametersOfMessagesSent(string $messageCode): array
    {
        return array_values(
            array_map(
                static fn (array $message): array => $message['parameters'],
                array_filter(
                    $this->customerMessages,
                    static fn (array $message): bool => $message['code'] === $messageCode,
                ),
            ),
        );
    }
}
