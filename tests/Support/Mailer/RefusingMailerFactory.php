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

namespace Thelia\Tests\Support\Mailer;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;

/**
 * A mail factory whose mail server refuses some addresses, as an SMTP server does when it
 * is down or turns a recipient away.
 */
final class RefusingMailerFactory extends CapturingMailerFactory
{
    /**
     * @param list<string> $refused
     */
    public function __construct(TemplateHelperInterface $templateHelper, ParserResolver $parserResolver, MailerInterface $mailer, TransportInterface $transport, private readonly array $refused)
    {
        parent::__construct($templateHelper, $parserResolver, $mailer, $transport);
    }

    public function sendNow(Email $message): void
    {
        foreach ($message->getTo() as $address) {
            if (\in_array($address->getAddress(), $this->refused, true)) {
                throw new TransportException('The server refused the recipient.');
            }
        }

        parent::sendNow($message);
    }
}
