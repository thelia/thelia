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

/**
 * A mailer factory that keeps what it was asked to send instead of sending it.
 *
 * It stands in for the transport only: everything the factory decides before
 * handing a message over — the recipients of a shop notification, the locale a
 * customer message is rendered in, the parameters the calling code chose — is
 * resolved by the real code and recorded here. A test can therefore assert on
 * what the caller is responsible for, the wording around it belonging to the
 * email template, which a shop is free to replace.
 */
class RecordingMailerFactory extends MailerFactory
{
    /**
     * @var list<array{
     *     code: string,
     *     from: array<string, string>,
     *     to: array<string, string>,
     *     parameters: array<string, mixed>,
     *     locale: string|null,
     *     replyTo: array<string, string>,
     * }>
     */
    public array $messages = [];

    public function sendEmailMessageOrFail(
        string $messageCode,
        array $from,
        array $to,
        array $messageParameters = [],
        ?string $locale = null,
        array $cc = [],
        array $bcc = [],
        array $replyTo = [],
    ): void {
        $this->messages[] = [
            'code' => $messageCode,
            'from' => $from,
            'to' => $to,
            'parameters' => $messageParameters,
            'locale' => $locale,
            'replyTo' => $replyTo,
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
                    $this->messages,
                    static fn (array $message): bool => $message['code'] === $messageCode,
                ),
            ),
        );
    }
}
