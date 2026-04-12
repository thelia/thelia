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

namespace Thelia\Tests\Integration\Mailer;

use Symfony\Component\Mailer\MailerInterface;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Mailer\MailerFactory;
use Thelia\Test\IntegrationTestCase;

final class MailerFactoryTest extends IntegrationTestCase
{
    private MailerFactory $mailerFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailerFactory = new MailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
        );
    }

    public function testCreateSimpleEmailMessageBuildsCorrectEmail(): void
    {
        $email = $this->mailerFactory->createSimpleEmailMessage(
            ['sender@example.com' => 'Sender'],
            ['recipient@example.com' => 'Recipient'],
            'Test Subject',
            '<h1>Hello</h1>',
            'Hello',
        );

        self::assertSame('Test Subject', $email->getSubject());
        self::assertSame('<h1>Hello</h1>', $email->getHtmlBody());
        self::assertSame('Hello', $email->getTextBody());

        $from = $email->getFrom();
        self::assertCount(1, $from);
        self::assertSame('sender@example.com', $from[0]->getAddress());

        $to = $email->getTo();
        self::assertCount(1, $to);
        self::assertSame('recipient@example.com', $to[0]->getAddress());
    }

    public function testCreateSimpleEmailMessageWithCcBccReplyTo(): void
    {
        $email = $this->mailerFactory->createSimpleEmailMessage(
            ['from@test.com' => 'From'],
            ['to@test.com' => 'To'],
            'Subject',
            '<p>body</p>',
            'body',
            ['cc@test.com' => 'CC'],
            ['bcc@test.com' => 'BCC'],
            ['reply@test.com' => 'Reply'],
        );

        self::assertCount(1, $email->getCc());
        self::assertSame('cc@test.com', $email->getCc()[0]->getAddress());

        self::assertCount(1, $email->getBcc());
        self::assertSame('bcc@test.com', $email->getBcc()[0]->getAddress());

        self::assertCount(1, $email->getReplyTo());
        self::assertSame('reply@test.com', $email->getReplyTo()[0]->getAddress());
    }

    public function testSendDoesNotThrowWithNullTransport(): void
    {
        $email = $this->mailerFactory->createSimpleEmailMessage(
            ['test@example.com' => 'Test'],
            ['dest@example.com' => 'Dest'],
            'Null transport test',
            '<p>content</p>',
            'content',
        );

        // The test environment uses null:// transport, so send()
        // should complete without error.
        $this->mailerFactory->send($email);
        self::assertTrue(true);
    }
}
