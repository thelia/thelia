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

namespace Thelia\Tests\Support\Order;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Mailer\MailerFactory;

/**
 * A mailer whose transport refuses the message the way a real one does: the reason
 * names the recipient and carries the credentials of the transport.
 */
final class LeakingMailerFactory extends MailerFactory
{
    public const LEAKED_SECRET = 's3cr3t';

    /**
     * The transport is never reached: a null one stands in unless the test gives one.
     */
    public function __construct(TemplateHelperInterface $templateHelper, ParserResolver $parserResolver, MailerInterface $mailer, ?TransportInterface $transport = null)
    {
        parent::__construct($templateHelper, $parserResolver, $mailer, $transport ?? new NullTransport());
    }

    public function send(Email $message): void
    {
        throw new TransportException('Connection to smtp://postmaster:'.self::LEAKED_SECRET.'@mail.example.com refused while writing to '.$message->getTo()[0]->getAddress());
    }
}
