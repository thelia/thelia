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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\LangQuery;
use Thelia\Model\Message;
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

    public function testCreateEmailMessageRestoresTheSessionLangWhenRenderingFails(): void
    {
        $session = $this->getService(RequestStack::class)->getMainRequest()->getSession();

        $french = LangQuery::create()->findOneByLocale('fr_FR');
        self::assertNotNull($french);
        $session->setLang($french);

        // No body and no template file: buildMessage() throws once the required language has
        // already been written into the session.
        $message = new Message();
        $message->setName('test_unrenderable_message');
        $message->setLocale('en_US');
        $message->setSubject('Subject');
        $message->save();

        try {
            $this->mailerFactory->createEmailMessage(
                'test_unrenderable_message',
                ['sender@example.com' => 'Sender'],
                ['recipient@example.com' => 'Recipient'],
                [],
                'en_US',
            );
            self::fail('Rendering was expected to fail.');
        } catch (\Exception) {
            // The failure is the point: sendEmailMessage() swallows it in production.
        }

        self::assertSame('fr_FR', $session->getLang()->getLocale());
    }

    public function testCreateEmailMessageRestoresTheParserTemplateWhenRenderingFails(): void
    {
        $templateHelper = $this->getService(TemplateHelperInterface::class);
        $frontTemplate = $templateHelper->getActiveFrontTemplate();

        // Parsers are shared services: the template definition MailerFactory leaves behind is
        // the one every later render sees, hence resolving the very same instance here.
        $parser = $this->getService(ParserResolver::class)->getParser(
            $templateHelper->getActiveMailTemplate()->getAbsolutePath(),
            'order_confirmation',
        );

        $initialTemplate = $parser->getTemplateDefinition();
        $parser->setTemplateDefinition($frontTemplate);

        // An existing template file so a parser is resolved, and a subject that cannot be
        // compiled so rendering throws once the mail template has been pushed.
        $message = new Message();
        $message->setName('test_unrenderable_subject_message');
        $message->setLocale('en_US');
        $message->setSubject('{{ ');
        $message->setHtmlTemplateFileName('order_confirmation.html');
        $message->save();

        try {
            $this->mailerFactory->createEmailMessage(
                'test_unrenderable_subject_message',
                ['sender@example.com' => 'Sender'],
                ['recipient@example.com' => 'Recipient'],
                [],
                'en_US',
            );
            self::fail('Rendering was expected to fail.');
        } catch (\Exception) {
            // The failure is the point: sendEmailMessage() swallows it in production.
        }

        $templateAfterFailure = $parser->getTemplateDefinition();

        if (null !== $initialTemplate) {
            $parser->setTemplateDefinition($initialTemplate);
        }

        self::assertSame($frontTemplate->getAbsolutePath(), $templateAfterFailure?->getAbsolutePath());
    }

    public function testCreateEmailMessageRendersInTheRequestedLocaleInAdminEnvironment(): void
    {
        $session = $this->getService(RequestStack::class)->getMainRequest()->getSession();

        $french = LangQuery::create()->findOneByLocale('fr_FR');
        $english = LangQuery::create()->findOneByLocale('en_US');
        self::assertNotNull($french);
        self::assertNotNull($english);

        // The administrator browses the back office in English, the customer is French.
        $session->setAdminLang($english);
        $session->setLang($french);

        $message = new Message();
        $message->setName('test_admin_triggered_customer_message');
        $message->setLocale('fr_FR');
        $message->setSubject('Sujet');
        $message->setHtmlMessage('<p>Corps</p>');
        $message->setTextMessage('Corps');
        $message->save();

        $renderedLocales = [];
        $parser = $this->createMock(ParserInterface::class);
        $parser->method('getRequest')->willReturn($this->getService(RequestStack::class)->getMainRequest());
        $parser->method('getTemplateHelper')->willReturn($this->getService(TemplateHelperInterface::class));
        $parser->method('renderString')->willReturnCallback(
            static function (string $templateText) use (&$renderedLocales, $session): string {
                // What the parsers and the translator actually read to localize a render.
                $renderedLocales[] = $session->getLang()->getLocale();

                return $templateText;
            },
        );

        $mailerFactory = new MailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->createParserResolverReturning($parser),
            $this->getService(MailerInterface::class),
        );

        $wasAdminEnvironment = Request::$isAdminEnv;
        Request::$isAdminEnv = true;

        try {
            // Guard: in an admin environment Session::getLang() reads the admin language, so
            // the assertions below cannot be satisfied by the front office language slot.
            self::assertSame('en_US', $session->getLang()->getLocale());

            $mailerFactory->createEmailMessage(
                'test_admin_triggered_customer_message',
                ['sender@example.com' => 'Sender'],
                ['customer@example.com' => 'Customer'],
                [],
                'fr_FR',
            );

            self::assertSame('en_US', $session->getAdminLang()->getLocale());
        } finally {
            Request::$isAdminEnv = $wasAdminEnvironment;
        }

        self::assertNotSame([], $renderedLocales);
        self::assertSame(['fr_FR'], array_values(array_unique($renderedLocales)));
        // The front office language of the session is left untouched by an admin-side send.
        self::assertSame('fr_FR', $session->getLang()->getLocale());
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

    private function createParserResolverReturning(ParserInterface $parser): ParserResolver
    {
        return new class($this->getService(RequestStack::class), $this->getService(TemplateHelperInterface::class), $parser) extends ParserResolver {
            public function __construct(
                RequestStack $requestStack,
                TemplateHelperInterface $templateHelper,
                private readonly ParserInterface $parser,
            ) {
                parent::__construct([], [], $requestStack, $templateHelper);
            }

            public function getParser(string $pathTemplate, ?string $templateName): ParserInterface
            {
                return $this->parser;
            }
        };
    }
}
