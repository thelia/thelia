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
use Symfony\Component\Mime\Email;
use Thelia\Mailer\MailerFactory;

/**
 * A mailer whose transport refuses the message the way a real one does: the reason
 * names the recipient and carries the credentials of the transport.
 */
final class LeakingMailerFactory extends MailerFactory
{
    public const LEAKED_SECRET = 's3cr3t';

    public function send(Email $message): void
    {
        throw new TransportException('Connection to smtp://postmaster:'.self::LEAKED_SECRET.'@mail.example.com refused while writing to '.$message->getTo()[0]->getAddress());
    }
}
