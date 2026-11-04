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

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Mailer\MailerFactory;

/**
 * The real mail factory, which also remembers what it was asked to render and what the
 * files attached to a message held when it went out (an attached file is read by the
 * transport, after the code that wrote it has removed it).
 */
class CapturingMailerFactory extends MailerFactory
{
    /** @var list<array<string, mixed>> */
    public array $renderedWith = [];

    /** @var list<string> */
    public array $attachedBodies = [];

    public function __construct(TemplateHelperInterface $templateHelper, ParserResolver $parserResolver, MailerInterface $mailer, TransportInterface $transport)
    {
        parent::__construct($templateHelper, $parserResolver, $mailer, $transport);
    }

    /**
     * @param array<string, string> $from
     * @param array<string, string> $to
     * @param array<string, mixed>  $messageParameters
     * @param array<string, string> $cc
     * @param array<string, string> $bcc
     * @param array<string, string> $replyTo
     */
    public function createEmailMessage(string $messageCode, array $from, array $to, array $messageParameters = [], ?string $locale = null, array $cc = [], array $bcc = [], array $replyTo = []): Email
    {
        $this->renderedWith[] = $messageParameters;

        return parent::createEmailMessage($messageCode, $from, $to, $messageParameters, $locale, $cc, $bcc, $replyTo);
    }

    public function sendNow(Email $message): void
    {
        foreach ($message->getAttachments() as $attachment) {
            $this->attachedBodies[] = $attachment->getBody();
        }

        parent::sendNow($message);
    }
}
