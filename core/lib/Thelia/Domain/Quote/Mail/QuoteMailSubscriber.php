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

namespace Thelia\Domain\Quote\Mail;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Domain\Quote\Document\QuoteDocumentContext;
use Thelia\Domain\Quote\Event\QuoteAcceptedEvent;
use Thelia\Domain\Quote\Event\QuoteCounteredEvent;
use Thelia\Domain\Quote\Event\QuoteEvent;
use Thelia\Domain\Quote\Event\QuoteEvents;
use Thelia\Domain\Quote\Event\QuoteRejectedEvent;
use Thelia\Domain\Quote\Event\QuoteRemindedEvent;
use Thelia\Domain\Quote\Event\QuoteRequestedEvent;
use Thelia\Domain\Quote\Event\QuoteSentEvent;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\Quote;
use Thelia\Model\QuoteRevision;
use Thelia\Model\QuoteRevisionQuery;

/**
 * Tells the people concerned when a quote moves, once the move is written.
 *
 * The buyer hears about what the shop sends them (a revision, a reminder), in the language frozen
 * on the quote. The shop managers hear about what the buyer does: a request, a counter-proposal,
 * an acceptance, a refusal. A mail that cannot be built or sent is logged and goes no further: a
 * quote has moved, and a mail is never worth taking that back.
 *
 * The link to the quote is the page of the buyer's account that shows it, when the storefront has
 * one (route `account_quote`, with the id of the quote as `quoteId`); a storefront without it gets
 * the mail with the reference of the quote and the link to the account. The managers get the link to
 * the quote in the back office (route `admin.quote.view`, with `id`) when the back office has one.
 */
final readonly class QuoteMailSubscriber implements EventSubscriberInterface
{
    public const string REQUESTED_MESSAGE = 'quote_requested';
    public const string SENT_MESSAGE = 'quote_sent';
    public const string COUNTERED_MESSAGE = 'quote_countered';
    public const string ACCEPTED_MESSAGE = 'quote_accepted';
    public const string REJECTED_MESSAGE = 'quote_rejected';

    public const string QUOTE_ROUTE = 'account_quote';
    public const string ADMIN_QUOTE_ROUTE = 'admin.quote.view';

    public function __construct(
        private MailerFactory $mailerFactory,
        private QuoteDocumentContext $context,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            QuoteEvents::REQUESTED => 'onRequested',
            QuoteEvents::SENT => 'onSent',
            QuoteEvents::REMINDED => 'onReminded',
            QuoteEvents::COUNTERED => 'onCountered',
            QuoteEvents::ACCEPTED => 'onAccepted',
            QuoteEvents::REJECTED => 'onRejected',
        ];
    }

    public function onRequested(QuoteRequestedEvent $event): void
    {
        $this->toTheManagers($event, self::REQUESTED_MESSAGE);
    }

    public function onSent(QuoteSentEvent $event): void
    {
        $this->toTheBuyer($event, false);
    }

    public function onReminded(QuoteRemindedEvent $event): void
    {
        $this->toTheBuyer($event, true);
    }

    public function onCountered(QuoteCounteredEvent $event): void
    {
        $this->toTheManagers($event, self::COUNTERED_MESSAGE);
    }

    public function onAccepted(QuoteAcceptedEvent $event): void
    {
        $this->toTheManagers($event, self::ACCEPTED_MESSAGE);
    }

    public function onRejected(QuoteRejectedEvent $event): void
    {
        $this->toTheManagers($event, self::REJECTED_MESSAGE);
    }

    private function toTheBuyer(QuoteEvent $event, bool $isReminder): void
    {
        try {
            $quote = $event->getQuote();
            $revision = $this->revisionOf($event);
            /** @var Customer|null $customer Propel finds none for an orphan id. */
            $customer = $quote->getCustomer();

            if (null === $revision || null === $customer) {
                return;
            }

            $locale = $this->localeOfTheBuyer($quote);
            $parameters = $this->context->build($quote, $revision, $locale) + [
                'is_reminder' => $isReminder,
                'quote_path' => $this->pathToTheQuote($quote),
                'customer_id' => (int) $customer->getId(),
            ];

            $this->mailerFactory->sendEmailMessageOrFail(
                self::SENT_MESSAGE,
                [ConfigQuery::getStoreEmail() => ConfigQuery::getStoreName()],
                [$customer->getEmail() => trim(\sprintf('%s %s', (string) $customer->getFirstname(), (string) $customer->getLastname()))],
                $parameters,
                $locale,
            );
        } catch (\Throwable $throwable) {
            $this->logTheFailure($event, self::SENT_MESSAGE, $throwable);
        }
    }

    private function toTheManagers(QuoteEvent $event, string $messageCode): void
    {
        try {
            $quote = $event->getQuote();
            $revision = $this->revisionOf($event);

            if (null === $revision) {
                return;
            }

            $locale = Lang::getDefaultLanguage()->getLocale();
            $parameters = $this->context->build($quote, $revision, $locale) + [
                'admin_quote_path' => $this->pathToTheQuoteInTheBackOffice($quote),
            ];

            if ($event instanceof QuoteCounteredEvent) {
                $parameters['requested_changes'] = $this->context->requestedChanges($revision, (int) $quote->getCurrencyId(), $locale);
            }

            /** @var Customer|null $customer Propel finds none for an orphan id. */
            $customer = $quote->getCustomer();
            $replyTo = null === $customer ? [] : [$customer->getEmail() => $parameters['customer_name']];

            $this->mailerFactory->sendEmailToShopManagersOrFail($messageCode, $parameters, $replyTo);
        } catch (\Throwable $throwable) {
            $this->logTheFailure($event, $messageCode, $throwable);
        }
    }

    private function revisionOf(QuoteEvent $event): ?QuoteRevision
    {
        return QuoteRevisionQuery::create()
            ->filterByQuoteId($event->getQuote()->getId())
            ->filterByNumber($event->getRevisionNumber())
            ->orderById(Criteria::DESC)
            ->findOne();
    }

    private function localeOfTheBuyer(Quote $quote): string
    {
        $locale = $quote->getLocale();

        if (null !== $locale && '' !== $locale) {
            return $locale;
        }

        /** @var Customer|null $customer Propel finds none for an orphan id. */
        $customer = $quote->getCustomer();

        return $customer?->getCustomerLang()->getLocale() ?? Lang::getDefaultLanguage()->getLocale();
    }

    /**
     * The path of the page that shows the quote to its buyer, null while the storefront has none.
     */
    private function pathToTheQuote(Quote $quote): ?string
    {
        try {
            return $this->urlGenerator->generate(self::QUOTE_ROUTE, ['quoteId' => (int) $quote->getId()], UrlGeneratorInterface::ABSOLUTE_PATH);
        } catch (RoutingException) {
            return null;
        }
    }

    /**
     * The path of the page that shows the quote to the shop, null while no back office has one.
     */
    private function pathToTheQuoteInTheBackOffice(Quote $quote): ?string
    {
        try {
            return $this->urlGenerator->generate(self::ADMIN_QUOTE_ROUTE, ['id' => (int) $quote->getId()], UrlGeneratorInterface::ABSOLUTE_PATH);
        } catch (RoutingException) {
            return null;
        }
    }

    private function logTheFailure(QuoteEvent $event, string $messageCode, \Throwable $throwable): void
    {
        Tlog::getInstance()->addError(\sprintf(
            'The mail "%s" of the quote %s was not sent: %s',
            $messageCode,
            (string) $event->getQuote()->getRef(),
            $throwable->getMessage(),
        ));
    }
}
