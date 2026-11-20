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

namespace Thelia\Tests\Integration\Company;

use Symfony\Component\Mime\Email;
use Thelia\Mailer\MailerFactory;

/**
 * The real factory for everything it decides about the recipients; the message itself is a bare
 * one, since the wording of a template is not what is held here, and nothing leaves the process.
 */
final class OrderCopyMailerFactory extends MailerFactory
{
    /** @var list<Email> */
    public array $sent = [];

    public function createEmailMessage(string $messageCode, array $from, array $to, array $messageParameters = [], ?string $locale = null, array $cc = [], array $bcc = [], array $replyTo = []): Email
    {
        $email = (new Email())->subject($messageCode)->text($messageCode);
        $this->setupMessageHeaders($email, $from, $to, $cc, $bcc, $replyTo);

        return $email;
    }

    public function send(Email $message): void
    {
        $this->sent[] = $message;
    }

    /**
     * @return list<string>
     */
    public function copiesOf(int $index): array
    {
        return array_map(static fn ($address): string => $address->getAddress(), $this->sent[$index]->getCc());
    }

    /**
     * @return list<string>
     */
    public function recipientsOf(int $index): array
    {
        return array_map(static fn ($address): string => $address->getAddress(), $this->sent[$index]->getTo());
    }
}
