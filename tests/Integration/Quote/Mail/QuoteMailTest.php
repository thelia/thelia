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

namespace Thelia\Tests\Integration\Quote\Mail;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Quote\Document\QuoteDocumentContext;
use Thelia\Domain\Quote\DTO\QuoteItemRequestInput;
use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Domain\Quote\Event\QuoteAcceptedEvent;
use Thelia\Domain\Quote\Event\QuoteEvents;
use Thelia\Domain\Quote\Exception\QuoteTransitionRefusedException;
use Thelia\Domain\Quote\Mail\QuoteMailSubscriber;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Quote;
use Thelia\Tests\Integration\Quote\QuoteDomainTestCase;
use Thelia\Tests\Support\Mailer\RefusingMailerFactory;

/**
 * The mails of the life of a quote: who gets which one, with what, and that a mail which cannot
 * leave never undoes what happened to the quote.
 */
final class QuoteMailTest extends QuoteDomainTestCase
{
    private const MANAGER = 'manager@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $emailTemplate = $this->getService(TemplateHelperInterface::class)->getActiveMailTemplate()->getAbsolutePath();

        if (!is_file($emailTemplate.\DIRECTORY_SEPARATOR.'quote_sent.html.twig')) {
            self::markTestSkipped('The installed e-mail template has no quote messages yet.');
        }

        ConfigQuery::write('store_notification_emails', self::MANAGER);
        ConfigQuery::write('store_email', 'shop@example.com');
        ConfigQuery::resetCache();
    }

    public function testARequestIsToldToTheManagersOnly(): void
    {
        $buyer = $this->customer();
        $quote = $this->request($buyer, null, new QuoteRequestInput(comment: 'Delivery before Christmas, please'));

        self::assertEmailCount(1);
        $mail = $this->mail(0);
        self::assertSame([self::MANAGER], $this->recipients($mail));
        self::assertStringContainsString((string) $quote->getRef(), (string) $mail->getSubject());
        self::assertStringContainsString('Delivery before Christmas, please', (string) $mail->getHtmlBody());
        self::assertStringContainsString((string) $quote->getRef(), (string) $mail->getTextBody());
        self::assertSame([(string) $buyer->getEmail()], array_map(static fn ($address) => $address->getAddress(), $mail->getReplyTo()));
    }

    public function testASendingIsToldToTheBuyerOnlyWithTheRefAndTheTotal(): void
    {
        ['buyer' => $buyer, 'quote' => $quote] = $this->sent();

        $sendings = $this->mailsTo((string) $buyer->getEmail());
        self::assertCount(1, $sendings);
        self::assertCount(1, $this->mailsTo(self::MANAGER), 'the managers only heard of the request');
        self::assertStringContainsString((string) $quote->getRef(), (string) $sendings[0]->getSubject());
        self::assertStringContainsString((string) $quote->getRef(), (string) $sendings[0]->getHtmlBody());
        self::assertMatchesRegularExpression('/1\D?620[.,]00/', $this->squeeze((string) $sendings[0]->getHtmlBody()));
        self::assertMatchesRegularExpression('/1\D?620[.,]00/', $this->squeeze((string) $sendings[0]->getTextBody()));
    }

    public function testAReminderGoesToTheBuyerAgainAndSaysSo(): void
    {
        ['buyer' => $buyer, 'quote' => $quote] = $this->sent();
        $this->quotes->remind($this->reread($quote));

        $mails = $this->mailsTo((string) $buyer->getEmail());
        self::assertCount(2, $mails);
        self::assertNotSame($mails[0]->getHtmlBody(), $mails[1]->getHtmlBody());
        self::assertStringContainsString((string) $quote->getRef(), (string) $mails[1]->getHtmlBody());
    }

    public function testACounterProposalIsToldToTheManagersWithWhatTheBuyerAsks(): void
    {
        ['buyer' => $buyer, 'quote' => $quote, 'a' => $a] = $this->sent();
        $line = $this->lineOf($this->revisionOf($quote, 2), (string) $a->getRef());
        $before = \count($this->mailsTo(self::MANAGER));

        $this->quotes->counterOffer($this->reread($quote), $buyer, 2, [new QuoteItemRequestInput((int) $line->getId(), null, '85')]);

        $mails = $this->mailsTo(self::MANAGER);
        self::assertCount($before + 1, $mails);
        $mail = $mails[$before];
        self::assertStringContainsString((string) $quote->getRef(), (string) $mail->getSubject());
        self::assertMatchesRegularExpression('/x85[.,]00/', $this->squeeze((string) $mail->getHtmlBody()));
        self::assertMatchesRegularExpression('/x90[.,]00/', $this->squeeze((string) $mail->getHtmlBody()));
    }

    public function testARefusalIsToldToTheManagers(): void
    {
        ['buyer' => $buyer, 'quote' => $quote] = $this->sent();
        $before = \count($this->mailsTo(self::MANAGER));

        $this->quotes->reject($this->reread($quote), $buyer, 2);

        $mails = $this->mailsTo(self::MANAGER);
        self::assertCount($before + 1, $mails);
        self::assertStringContainsString((string) $quote->getRef(), (string) $mails[$before]->getSubject());
    }

    public function testAnAcceptanceIsToldToTheManagers(): void
    {
        ['quote' => $quote] = $this->sent();
        $before = \count($this->mailsTo(self::MANAGER));

        self::getContainer()->get('event_dispatcher')->dispatch(new QuoteAcceptedEvent($this->reread($quote), 2), QuoteEvents::ACCEPTED);

        $mails = $this->mailsTo(self::MANAGER);
        self::assertCount($before + 1, $mails);
        self::assertStringContainsString((string) $quote->getRef(), (string) $mails[$before]->getSubject());
    }

    public function testARefusedTransitionSendsNothing(): void
    {
        ['buyer' => $buyer, 'quote' => $quote] = $this->requested();
        $count = \count(self::getMailerMessages());

        try {
            $this->quotes->reject($this->reread($quote), $buyer, 1);
            self::fail('A quote that was not sent cannot be turned down.');
        } catch (QuoteTransitionRefusedException) {
        }

        self::assertCount($count, self::getMailerMessages());
    }

    public function testAMailThatCannotLeaveDoesNotUndoTheSending(): void
    {
        $scenario = $this->requested();
        $factory = new RefusingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
            self::getContainer()->get('mailer.transports'),
            [(string) $scenario['buyer']->getEmail()],
        );
        $this->quotesWith($this->subscriberWith($factory));

        $this->quotes->saveDraft($scenario['quote'], null, $this->draftOfTheShop($scenario));
        $sent = $this->quotes->send($this->reread($scenario['quote']), null);

        self::assertSame('quoted', $sent->getState());
        self::assertSame('quoted', $this->reread($scenario['quote'])->getState());
    }

    public function testAnythingThatGoesWrongBuildingTheMailIsSwallowedToo(): void
    {
        $scenario = $this->requested();
        $factory = new class($this->getService(TemplateHelperInterface::class), $this->getService(ParserResolver::class), $this->getService(MailerInterface::class), self::getContainer()->get('mailer.transports')) extends \Thelia\Tests\Support\Mailer\CapturingMailerFactory {
            public function createEmailMessage(string $messageCode, array $from, array $to, array $messageParameters = [], ?string $locale = null, array $cc = [], array $bcc = [], array $replyTo = []): Email
            {
                throw new \LogicException('The template is broken.');
            }
        };
        $this->quotesWith($this->subscriberWith($factory));

        $this->quotes->saveDraft($scenario['quote'], null, $this->draftOfTheShop($scenario));

        self::assertSame('quoted', $this->quotes->send($this->reread($scenario['quote']), null)->getState());
    }

    public function testTheMailLinksToTheQuotePageWhenTheStorefrontHasOne(): void
    {
        $generator = new class implements UrlGeneratorInterface {
            public function setContext(\Symfony\Component\Routing\RequestContext $context): void
            {
            }

            public function getContext(): \Symfony\Component\Routing\RequestContext
            {
                return new \Symfony\Component\Routing\RequestContext();
            }

            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return '/account/quote-page/'.$parameters['quoteId'];
            }
        };
        ['buyer' => $buyer, 'quote' => $quote] = $this->sentWith($generator);

        $mail = $this->mailsTo((string) $buyer->getEmail())[0];
        self::assertStringContainsString('/account/quote-page/'.$quote->getId(), (string) $mail->getHtmlBody());
        self::assertStringContainsString('/account/quote-page/'.$quote->getId(), (string) $mail->getTextBody());
    }

    public function testTheMailFallsBackToTheAccountWhenTheStorefrontHasNoQuotePage(): void
    {
        $generator = new class implements UrlGeneratorInterface {
            public function setContext(\Symfony\Component\Routing\RequestContext $context): void
            {
            }

            public function getContext(): \Symfony\Component\Routing\RequestContext
            {
                return new \Symfony\Component\Routing\RequestContext();
            }

            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                throw new RouteNotFoundException();
            }
        };
        ['buyer' => $buyer] = $this->sentWith($generator);

        $mail = $this->mailsTo((string) $buyer->getEmail())[0];
        self::assertMatchesRegularExpression('#href="[^"]*/account"#', (string) $mail->getHtmlBody());
        self::assertStringNotContainsString('quote-page', (string) $mail->getHtmlBody());
    }

    public function testTheManagersGetTheLinkToTheQuoteInTheBackOfficeWhenThereIsOne(): void
    {
        $generator = $this->generatorOf(static fn (string $name, array $parameters): string => 'admin.quote.view' === $name
            ? '/admin/quotes/'.$parameters['id']
            : throw new RouteNotFoundException());
        $this->quotesWith($this->subscriberUsing($generator));

        $quote = $this->request($this->customer());

        $mail = $this->mailsTo(self::MANAGER)[0];
        self::assertStringContainsString('/admin/quotes/'.$quote->getId(), (string) $mail->getHtmlBody());
        self::assertStringContainsString('/admin/quotes/'.$quote->getId(), (string) $mail->getTextBody());
    }

    public function testTheManagersGetNoLinkWhenTheBackOfficeHasNoQuotePage(): void
    {
        $generator = $this->generatorOf(static fn (string $name, array $parameters): string => throw new RouteNotFoundException());
        $this->quotesWith($this->subscriberUsing($generator));

        $this->request($this->customer());

        $mail = $this->mailsTo(self::MANAGER)[0];
        self::assertStringNotContainsString('admin/quotes', (string) $mail->getHtmlBody());
        self::assertStringNotContainsString('admin/quotes', (string) $mail->getTextBody());
        self::assertStringNotContainsString('back office', (string) $mail->getHtmlBody());
    }

    public function testEveryMailToTheManagersLinksTheQuoteInTheBackOffice(): void
    {
        $generator = $this->generatorOf(static fn (string $name, array $parameters): string => 'admin.quote.view' === $name
            ? '/admin/quotes/'.$parameters['id']
            : throw new RouteNotFoundException());

        ['buyer' => $buyer, 'quote' => $countered, 'a' => $a] = $this->sent();
        $line = $this->lineOf($this->revisionOf($countered, 2), (string) $a->getRef());
        ['buyer' => $refuser, 'quote' => $refused] = $this->sent();
        ['quote' => $accepted] = $this->sent();
        $subscriber = $this->subscriberUsing($generator);
        $this->quotesWith($subscriber);

        $this->quotes->counterOffer($this->reread($countered), $buyer, 2, [new QuoteItemRequestInput((int) $line->getId(), null, '85')]);
        $this->quotes->reject($this->reread($refused), $refuser, 2);
        $subscriber->onAccepted(new QuoteAcceptedEvent($this->reread($accepted), 2));

        foreach ([$countered, $refused, $accepted] as $quote) {
            $mail = $this->mailToTheManagersAbout($quote);
            self::assertStringContainsString('/admin/quotes/'.$quote->getId(), (string) $mail->getHtmlBody(), (string) $quote->getRef());
            self::assertStringContainsString('/admin/quotes/'.$quote->getId(), (string) $mail->getTextBody(), (string) $quote->getRef());
        }
    }

    public function testTheBuyerNeverGetsTheLinkOfTheBackOffice(): void
    {
        $generator = $this->generatorOf(static fn (string $name, array $parameters): string => 'admin.quote.view' === $name
            ? '/admin/quotes/'.$parameters['id']
            : '/account/quote-page/'.$parameters['quoteId']);
        ['buyer' => $buyer] = $this->sentWith($generator);

        $mail = $this->mailsTo((string) $buyer->getEmail())[0];
        self::assertStringNotContainsString('admin/quotes', (string) $mail->getHtmlBody());
    }

    public function testWhatComesFromTheShopOrFromALineIsEscaped(): void
    {
        $scenario = $this->requested();
        $this->quotes->saveDraft($scenario['quote'], null, $this->draftFrom(
            $this->revisionOf($scenario['quote'], 1),
            discount: '0',
            postage: '0',
            merchantNote: '<p>Hello <b>there</b> <script>alert(1)</script></p>',
        ));
        $revision = $this->revisionOf($scenario['quote'], 2);
        $this->linesOf($revision)[0]->setTitle('<img src=x onerror=alert(2)>Product')->save();
        $this->quotes->send($this->reread($scenario['quote']), null);

        $html = (string) $this->mailsTo((string) $scenario['buyer']->getEmail())[0]->getHtmlBody();

        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringContainsString('&lt;img src=x', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<b>there', $html);
        self::assertStringContainsString('Hello there', $this->squeezeTags($html));
    }

    /**
     * @return array{buyer: \Thelia\Model\Customer, quote: Quote, a: \Thelia\Model\Product, b: \Thelia\Model\Product, c: \Thelia\Model\Product}
     */
    private function requested(): array
    {
        return $this->requestedScenario();
    }

    /**
     * @return array{buyer: \Thelia\Model\Customer, quote: Quote, a: \Thelia\Model\Product, b: \Thelia\Model\Product, c: \Thelia\Model\Product}
     */
    private function sent(): array
    {
        $scenario = $this->requestedScenario();
        $this->quotes->saveDraft($scenario['quote'], null, $this->draftOfTheShop($scenario));
        $this->quotes->send($this->reread($scenario['quote']), null);

        return $scenario;
    }

    /**
     * @return array{buyer: \Thelia\Model\Customer, quote: Quote}
     */
    private function sentWith(UrlGeneratorInterface $generator): array
    {
        $scenario = $this->requestedScenario();
        $this->quotesWith(new QuoteMailSubscriber(
            $this->getService(\Thelia\Mailer\MailerFactory::class),
            $this->getService(QuoteDocumentContext::class),
            $generator,
        ));
        $this->quotes->saveDraft($scenario['quote'], null, $this->draftOfTheShop($scenario));
        $this->quotes->send($this->reread($scenario['quote']), null);

        return $scenario;
    }

    private function mailToTheManagersAbout(Quote $quote): Email
    {
        $mails = array_values(array_filter(
            $this->mailsTo(self::MANAGER),
            static fn (Email $mail): bool => str_contains((string) $mail->getSubject(), (string) $quote->getRef()),
        ));

        return $mails[array_key_last($mails)];
    }

    private function subscriberUsing(UrlGeneratorInterface $generator): QuoteMailSubscriber
    {
        return new QuoteMailSubscriber(
            $this->getService(\Thelia\Mailer\MailerFactory::class),
            $this->getService(QuoteDocumentContext::class),
            $generator,
        );
    }

    /**
     * @param \Closure(string, array<string, mixed>): string $path
     */
    private function generatorOf(\Closure $path): UrlGeneratorInterface
    {
        return new class($path) implements UrlGeneratorInterface {
            public function __construct(private readonly \Closure $path)
            {
            }

            public function setContext(\Symfony\Component\Routing\RequestContext $context): void
            {
            }

            public function getContext(): \Symfony\Component\Routing\RequestContext
            {
                return new \Symfony\Component\Routing\RequestContext();
            }

            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return ($this->path)($name, $parameters);
            }
        };
    }

    private function subscriberWith(\Thelia\Mailer\MailerFactory $factory): QuoteMailSubscriber
    {
        return new QuoteMailSubscriber($factory, $this->getService(QuoteDocumentContext::class), self::getContainer()->get('router'));
    }

    /**
     * The facade, told to a dispatcher that holds this subscriber alone.
     */
    private function quotesWith(QuoteMailSubscriber $subscriber): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($subscriber);

        $this->quotes = new \Thelia\Domain\Quote\QuoteFacade(
            $dispatcher,
            $this->clock,
            new \Thelia\Domain\Quote\QuoteTransitions(),
            $this->quoteAccess,
            $this->getService(\Thelia\Domain\Quote\Service\QuoteTotalsCalculator::class),
            $this->getService(\Thelia\Domain\Quote\Service\QuoteRefAllocator::class),
            $this->getService(\Thelia\Domain\Quote\Service\QuoteLineWriter::class),
            $this->getService(\Thelia\Domain\Quote\Service\QuoteCatalogPrices::class),
            new \Thelia\Domain\Quote\Service\QuoteNoteSanitizer(),
            new \Thelia\Domain\Quote\Service\QuoteRevisionGuard(),
            $this->getService(\Thelia\Domain\Quote\Service\QuoteRevisionFreezer::class),
            $this->getService(\Thelia\Domain\Quote\Acceptance\QuoteSellability::class),
        );
    }

    private function mail(int $index): Email
    {
        $message = self::getMailerMessage($index);
        self::assertInstanceOf(Email::class, $message);

        return $message;
    }

    /**
     * @return list<string>
     */
    private function recipients(Email $mail): array
    {
        return array_map(static fn ($address): string => $address->getAddress(), $mail->getTo());
    }

    /**
     * @return list<Email>
     */
    private function mailsTo(string $address): array
    {
        $mails = [];

        foreach (self::getMailerMessages() as $message) {
            if ($message instanceof Email && \in_array($address, $this->recipients($message), true)) {
                $mails[] = $message;
            }
        }

        return $mails;
    }

    private function squeeze(string $text): string
    {
        return (string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $text);
    }

    private function squeezeTags(string $html): string
    {
        return (string) preg_replace('/\s+/', ' ', strip_tags($html));
    }
}
