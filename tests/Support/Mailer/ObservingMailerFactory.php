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
 * A mail factory that hands what it is about to send to a callback, and whose server goes
 * down for good once it has sent a given number of mails: the nearest a test comes to a
 * process killed in the middle of a send.
 */
final class ObservingMailerFactory extends CapturingMailerFactory
{
    private int $sent = 0;

    /**
     * @param \Closure(int, Email): void $beforeEachSend told the number of mails already sent and the one about to go
     */
    public function __construct(TemplateHelperInterface $templateHelper, ParserResolver $parserResolver, MailerInterface $mailer, TransportInterface $transport, private readonly int $downAfter, private readonly \Closure $beforeEachSend)
    {
        parent::__construct($templateHelper, $parserResolver, $mailer, $transport);
    }

    public function sendNow(Email $message): void
    {
        ($this->beforeEachSend)($this->sent, $message);

        if ($this->sent >= $this->downAfter) {
            throw new TransportException('The server went down.');
        }

        ++$this->sent;
        parent::sendNow($message);
    }
}
