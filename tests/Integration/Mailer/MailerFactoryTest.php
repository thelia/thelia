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
use Symfony\Component\Mailer\Transport\NullTransport;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
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
            new NullTransport(),
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

    public function testCreateEmailMessageInterpolatesTheSmartyPlaceholdersOfAMessageSeededForThelia2(): void
    {
        // A module ported from Thelia 2 whose setup.sql still seeds the Smarty wording:
        // without the rewrite, the customer reads the placeholder itself in the subject line.
        $message = new Message();
        $message->setName('test_legacy_smarty_message');
        $message->setLocale('en_US');
        $message->setSubject('Payment of order {$order_ref}');
        $message->setHtmlMessage('<p>Order {$order_ref} is paid.</p>');
        $message->setTextMessage('Order {$order_ref} is paid.');
        $message->save();

        $email = $this->mailerFactory->createEmailMessage(
            'test_legacy_smarty_message',
            ['sender@example.com' => 'Sender'],
            ['recipient@example.com' => 'Recipient'],
            ['order_ref' => 'ORD-42'],
            'en_US',
        );

        self::assertSame('Payment of order ORD-42', $email->getSubject());
        self::assertStringContainsString('ORD-42', (string) $email->getHtmlBody());
        self::assertStringContainsString('ORD-42', (string) $email->getTextBody());
    }

    public function testCreateEmailMessageResolvesTheConfigurationTagOfASubjectMigratedFromThelia2(): void
    {
        // The wording the Thelia 2 seed gave its own messages, kept by every migrated shop:
        // without the rewrite, the customer reads the Smarty tag in the subject line.
        ConfigQuery::write('store_name', 'Corner Shop');

        $message = new Message();
        $message->setName('test_thelia2_config_subject');
        $message->setLocale('en_US');
        $message->setSubject('Your order {$order_ref} at {config key="store_name"}{config key="shop\'s missing key"}');
        $message->setHtmlMessage('<p>Order {$order_ref}</p>');
        $message->setTextMessage('Order {$order_ref}');
        $message->save();

        $email = $this->mailerFactory->createEmailMessage(
            'test_thelia2_config_subject',
            ['sender@example.com' => 'Sender'],
            ['recipient@example.com' => 'Recipient'],
            ['order_ref' => 'ORD-42'],
            'en_US',
        );

        // The second tag names a key with a quote and no value: it compiles, and reads nothing.
        self::assertSame('Your order ORD-42 at Corner Shop', $email->getSubject());
    }

    public function testTheSubjectIsRenderedAsPlainTextWhileTheHtmlBodyStaysEscaped(): void
    {
        // A subject is a header, not HTML: the `&` of the shop name or of what the visitor
        // typed must not reach the inbox as `&amp;`. The HTML body is HTML, and keeps escaping.
        ConfigQuery::write('store_name', 'R&D Shop');
        $this->saveContactMessage('test_contact_subject_with_entities');

        $email = $this->mailerFactory->createEmailMessage(
            'test_contact_subject_with_entities',
            ['sender@example.com' => 'Sender'],
            ['recipient@example.com' => 'Recipient'],
            ['subject' => 'Question & "delay" <urgent>'],
            'en_US',
        );

        self::assertSame('R&D Shop - Contact: Question & "delay" <urgent>', $email->getSubject());
        self::assertStringContainsString(
            '<p>Question &amp; &quot;delay&quot; &lt;urgent&gt;</p>',
            (string) $email->getHtmlBody(),
        );
    }

    public function testALineBreakInASubjectVariableNeverOpensANewHeader(): void
    {
        ConfigQuery::write('store_name', 'Corner Shop');
        $this->saveContactMessage('test_contact_subject_with_line_breaks');

        $email = $this->mailerFactory->createEmailMessage(
            'test_contact_subject_with_line_breaks',
            ['sender@example.com' => 'Sender'],
            ['recipient@example.com' => 'Recipient'],
            ['subject' => "Question\r\nBcc: intruder@example.com\nX-Injected: yes"],
            'en_US',
        );

        $headers = strstr($email->toString(), "\r\n\r\n", true);
        self::assertIsString($headers);
        self::assertDoesNotMatchRegularExpression('/^(Bcc|X-Injected):/mi', $headers);
        // Symfony Mime already encodes the break rather than ending the header with it, but the
        // subject a mail client or an API transport reads must still be a single line.
        self::assertSame('Corner Shop - Contact: Question Bcc: intruder@example.com X-Injected: yes', $email->getSubject());
    }

    private function saveContactMessage(string $name): void
    {
        $message = new Message();
        $message->setName($name);
        $message->setLocale('en_US');
        $message->setSubject('{{ config(\'store_name\') }} - Contact: {{ subject }}');
        $message->setHtmlMessage('<p>{{ subject }}</p>');
        $message->setTextMessage('{{ subject }}');
        $message->save();
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
            new NullTransport(),
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

    public function testCreateEmailMessageHandsTheLanguageOfTheMessageToTheParserWhenThereIsNoSession(): void
    {
        $german = LangQuery::create()->findOneByLocale('de_DE');
        self::assertNotNull($german);
        $this->saveStoredBodyMessage('test_message_sent_from_a_command', 'de_DE');

        $rendered = [];
        $parser = $this->createRecordingParser(null, $rendered);

        $this->createMailerFactoryWith($parser)->createEmailMessage(
            'test_message_sent_from_a_command',
            ['sender@example.com' => 'Sender'],
            ['customer@example.com' => 'Customer'],
            [],
            'de_DE',
        );

        self::assertNotSame([], $rendered);
        self::assertSame(['locale' => 'de_DE', 'lang_code' => 'de', 'lang_id' => $german->getId()], end($rendered));
    }

    public function testTheLanguageOfAMessageSentWithoutASessionDoesNotLeakIntoTheNextRender(): void
    {
        $this->saveStoredBodyMessage('test_message_sent_from_a_command', 'de_DE');
        $this->saveStoredBodyMessage('test_message_of_an_unknown_locale', 'xx_XX');

        $rendered = [];
        $parser = $this->createRecordingParser(null, $rendered);
        $mailerFactory = $this->createMailerFactoryWith($parser);

        foreach (['test_message_sent_from_a_command' => 'de_DE', 'test_message_of_an_unknown_locale' => 'xx_XX'] as $name => $locale) {
            $mailerFactory->createEmailMessage($name, ['sender@example.com' => 'Sender'], ['customer@example.com' => 'Customer'], [], $locale);
        }

        // The second mail has no language to hand over (unknown locale): it must not read the German one.
        self::assertNotSame([], $rendered);
        self::assertSame(['locale' => null, 'lang_code' => null, 'lang_id' => null], end($rendered));
    }

    public function testCreateEmailMessageAssignsNoLanguageToTheParserWhenThereIsASession(): void
    {
        $this->saveStoredBodyMessage('test_message_sent_from_a_request', 'de_DE');

        $rendered = [];
        $parser = $this->createRecordingParser($this->getService(RequestStack::class)->getMainRequest(), $rendered);

        $this->createMailerFactoryWith($parser)->createEmailMessage(
            'test_message_sent_from_a_request',
            ['sender@example.com' => 'Sender'],
            ['customer@example.com' => 'Customer'],
            [],
            'de_DE',
        );

        self::assertNotSame([], $rendered);
        foreach ($rendered as $languageVariables) {
            self::assertSame([], $languageVariables, 'The language of the session, swapped by the factory, is the one to use.');
        }
    }

    private function saveStoredBodyMessage(string $name, string $locale): void
    {
        $message = new Message();
        $message->setName($name);
        $message->setLocale($locale);
        $message->setSubject('Subject');
        $message->setHtmlMessage('<p>Text</p>');
        $message->setTextMessage('Text');
        $message->save();
    }

    /**
     * @param list<array<string, mixed>> $rendered filled, at each render, with the language variables the parser holds
     */
    private function createRecordingParser(?\Symfony\Component\HttpFoundation\Request $request, array &$rendered): ParserInterface
    {
        $assigned = [];
        $parser = $this->createMock(ParserInterface::class);
        $parser->method('getRequest')->willReturn($request);
        $parser->method('getTemplateHelper')->willReturn($this->getService(TemplateHelperInterface::class));
        $parser->method('assign')->willReturnCallback(
            static function (string|array $variable, mixed $value = null) use (&$assigned): mixed {
                foreach (\is_array($variable) ? $variable : [$variable => $value] as $name => $assignedValue) {
                    if (\in_array($name, ['locale', 'lang_code', 'lang_id'], true)) {
                        $assigned[$name] = $assignedValue;
                    }
                }

                return null;
            },
        );
        $parser->method('renderString')->willReturnCallback(
            static function (string $templateText) use (&$assigned, &$rendered): string {
                $rendered[] = $assigned;

                return $templateText;
            },
        );

        return $parser;
    }

    private function createMailerFactoryWith(ParserInterface $parser): MailerFactory
    {
        return new MailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->createParserResolverReturning($parser),
            $this->getService(MailerInterface::class),
            $this->getService(OrderHistoryRecorder::class),
        );
    }

    public function testAMessageWithoutATemplateFileRendersItsStoredBody(): void
    {
        // No template file at all: the message renders from its database-stored body, so
        // there is no file for a parser to claim. Resolving a parser from the message code
        // reports a missing resource, and sendEmailMessage() would swallow it: the mail is
        // silently lost. The default parser has to render the stored body instead.
        $message = new Message();
        $message->setName('test_body_only_message');
        $message->setLocale('en_US');
        $message->setSubject('Subject');
        $message->setHtmlMessage('<p>Stored body</p>');
        $message->setTextMessage('Stored body');
        $message->save();

        $parser = $this->createMock(ParserInterface::class);
        $parser->method('getRequest')->willReturn($this->getService(RequestStack::class)->getMainRequest());
        $parser->method('getTemplateHelper')->willReturn($this->getService(TemplateHelperInterface::class));
        $parser->method('renderString')->willReturnArgument(0);

        $mailerFactory = new MailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->createParserResolverWhereNoParserClaimsAView($parser),
            $this->getService(MailerInterface::class),
            new NullTransport(),
        );

        $email = $mailerFactory->createEmailMessage(
            'test_body_only_message',
            ['sender@example.com' => 'Sender'],
            ['recipient@example.com' => 'Recipient'],
            [],
            'en_US',
        );

        self::assertSame('<p>Stored body</p>', $email->getHtmlBody());
        self::assertSame('Stored body', $email->getTextBody());
    }

    public function testAMessageWithoutASubjectInTheRequestedLocaleFallsBackToTheDefaultLanguage(): void
    {
        $defaultLocale = LangQuery::create()->findOneByByDefault(1)?->getLocale();
        self::assertIsString($defaultLocale);
        self::assertNotSame('it_IT', $defaultLocale);

        // The shape of the gap the 3.0 seed shipped: the body is translated, the subject
        // of that same locale never was.
        $message = new Message();
        $message->setName('test_message_without_italian_subject');
        $message->setLocale($defaultLocale);
        $message->setSubject('Default subject');
        $message->setHtmlMessage('<p>Default body</p>');
        $message->setTextMessage('Default body');
        $message->setLocale('it_IT');
        $message->setHtmlMessage('<p>Corpo</p>');
        $message->setTextMessage('Corpo');
        $message->save();

        $email = $this->createStoredBodyMailerFactory()->createEmailMessage(
            'test_message_without_italian_subject',
            ['sender@example.com' => 'Sender'],
            ['recipient@example.com' => 'Recipient'],
            [],
            'it_IT',
        );

        self::assertSame('Default subject', $email->getSubject());
        // The fallback is the subject alone: the body of the requested locale is untouched.
        self::assertSame('<p>Corpo</p>', $email->getHtmlBody());
    }

    public function testAMessageWithoutASubjectInAnyLocaleIsStillSent(): void
    {
        // Defined behaviour, and the reason the model logs it: a mail carrying an order
        // confirmation or an activation link is worth more delivered without a subject
        // line than not delivered at all.
        $message = new Message();
        $message->setName('test_message_without_any_subject');
        $message->setLocale('it_IT');
        $message->setHtmlMessage('<p>Corpo</p>');
        $message->setTextMessage('Corpo');
        $message->save();

        $email = $this->createStoredBodyMailerFactory()->createEmailMessage(
            'test_message_without_any_subject',
            ['sender@example.com' => 'Sender'],
            ['recipient@example.com' => 'Recipient'],
            [],
            'it_IT',
        );

        self::assertSame('', $email->getSubject());
        self::assertSame('<p>Corpo</p>', $email->getHtmlBody());
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

    /**
     * A factory whose parser renders what it is given, for messages that carry their body
     * in the database rather than in a template file of the mail theme.
     */
    private function createStoredBodyMailerFactory(): MailerFactory
    {
        $parser = $this->createMock(ParserInterface::class);
        $parser->method('getRequest')->willReturn($this->getService(RequestStack::class)->getMainRequest());
        $parser->method('getTemplateHelper')->willReturn($this->getService(TemplateHelperInterface::class));
        $parser->method('renderString')->willReturnArgument(0);

        return new MailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->createParserResolverWhereNoParserClaimsAView($parser),
            $this->getService(MailerInterface::class),
            new NullTransport(),
        );
    }

    /**
     * The situation of an install whose parsers all verify that a template file exists
     * (the Twig parser always did, the Smarty parser does since TheliaSmarty 3.0.1): a
     * view no theme ships is claimed by nobody, and only getDefaultParser() answers.
     */
    private function createParserResolverWhereNoParserClaimsAView(ParserInterface $defaultParser): ParserResolver
    {
        return new class($this->getService(RequestStack::class), $this->getService(TemplateHelperInterface::class), $defaultParser) extends ParserResolver {
            public function __construct(
                RequestStack $requestStack,
                TemplateHelperInterface $templateHelper,
                private readonly ParserInterface $defaultParser,
            ) {
                parent::__construct([], [], $requestStack, $templateHelper);
            }

            public function getParser(string $pathTemplate, ?string $templateName): ParserInterface
            {
                throw new ResourceNotFoundException(\sprintf('Parser for template %s not found', $templateName));
            }

            public function getDefaultParser(): ParserInterface
            {
                return $this->defaultParser;
            }
        };
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

            public function getDefaultParser(): ParserInterface
            {
                return $this->parser;
            }
        };
    }
}
