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

namespace Thelia\Tests\Integration\Domain\Subscription;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Subscription\Enum\ActorType;
use Thelia\Domain\Subscription\Enum\RecurringChargeOutcome;
use Thelia\Domain\Subscription\EventListener\SubscriptionNotificationListener;
use Thelia\Domain\Subscription\SubscriptionLifecycle;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\LangQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Payment\RecurringTestPaymentModule;

/**
 * What the customer, and on a suspension the shop, are told. The templates live in the
 * theme of the e-mails: the mailer is a recorder here, and what is checked is who is written
 * to, with which message and which variables.
 */
final class SubscriptionNotificationTest extends ActionIntegrationTestCase
{
    use SubscriptionScenario;

    private object $recorder;

    /** @var list<array{string, \Closure}> */
    private array $listeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTheSubscriptionShop();

        $recorder = new class extends MailerFactory {
            /** @var list<array{code: string, to: string, locale: ?string, variables: array<string, mixed>}> */
            public array $log = [];

            public function __construct()
            {
            }

            public function sendEmailMessageOrFail(string $messageCode, array $from, array $to, array $messageParameters = [], ?string $locale = null, array $cc = [], array $bcc = [], array $replyTo = []): void
            {
                $this->log[] = ['code' => $messageCode, 'to' => (string) array_key_first($to), 'locale' => $locale, 'variables' => $messageParameters];
            }

            public function sendEmailToShopManagersOrFail(string $messageCode, array $messageParameters = [], array $replyTo = []): void
            {
                $this->log[] = ['code' => $messageCode, 'to' => 'shop', 'locale' => null, 'variables' => $messageParameters];
            }
        };
        $this->recorder = $recorder;
        $listener = new SubscriptionNotificationListener($recorder, $this->getService(RequestStack::class));
        $dispatcher = $this->getService(EventDispatcherInterface::class);

        foreach ([
            TheliaEvents::SUBSCRIPTION_INSTALLMENT_FAILED => $listener->tellTheCustomerThePaymentFailed(...),
            TheliaEvents::SUBSCRIPTION_STATUS_CHANGE => $listener->tellWhoMustKnowTheStatusChanged(...),
        ] as $event => $callable) {
            $closure = \Closure::fromCallable($callable);
            $dispatcher->addListener($event, $closure, 512);
            $this->listeners[] = [$event, $closure];
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->listeners as [$event, $closure]) {
            $this->getService(EventDispatcherInterface::class)->removeListener($event, $closure);
        }

        $this->tearDownTheSubscriptionShop();

        parent::tearDown();
    }

    public function testADeclinedChargeThatWillBeTriedAgainTellsTheCustomer(): void
    {
        [$subscription] = $this->aSubscription();
        RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::Declined;

        $this->moveTo('2028-02-29 10:00:00');
        $this->biller()->bill((int) $subscription->getId());

        $messages = $this->recorder->log;
        self::assertCount(1, $messages);
        self::assertSame('subscription_payment_failed', $messages[0]['code']);
        self::assertSame($subscription->getCustomer()->getEmail(), $messages[0]['to']);
        self::assertSame(LangQuery::create()->findPk($subscription->getLangId())->getLocale(), $messages[0]['locale'], 'In the language the subscription was taken in.');
        self::assertSame($subscription->getRef(), $messages[0]['variables']['subscription_ref']);
        self::assertSame(2, $messages[0]['variables']['installment_number']);
        self::assertSame('declined', $messages[0]['variables']['failure_kind']);
        self::assertSame('2028-03-02', $messages[0]['variables']['retry_date']);
        self::assertSame('Visa ending 4242', $messages[0]['variables']['payment_method_label']);
        self::assertArrayHasKey('order_ref', $messages[0]['variables']);
    }

    public function testAProviderThatDoesNotAnswerTellsNobody(): void
    {
        [$subscription] = $this->aSubscription();
        RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::Retryable;

        $this->moveTo('2028-02-29 10:00:00');
        $this->biller()->bill((int) $subscription->getId());

        self::assertSame([], $this->recorder->log);
    }

    public function testASuspensionTellsTheCustomerAndTheShopOnceAndNotThePaymentFailureOfThatCharge(): void
    {
        [$subscription] = $this->aSubscription();
        RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::Declined;

        foreach (['2028-02-29 10:00:00', '2028-03-02 10:00:00', '2028-03-04 10:00:00'] as $moment) {
            $this->moveTo($moment);
            $this->biller()->bill((int) $subscription->getId());
        }

        self::assertSame(
            ['subscription_payment_failed', 'subscription_payment_failed', 'subscription_suspended', 'subscription_suspended'],
            array_column($this->recorder->log, 'code'),
        );
        $suspended = \array_slice($this->recorder->log, 2);
        self::assertSame([$subscription->getCustomer()->getEmail(), 'shop'], array_column($suspended, 'to'));
        self::assertSame(['customer', 'shop'], array_column(array_column($suspended, 'variables'), 'recipient'));
        self::assertSame(3, $suspended[0]['variables']['failure_count']);
    }

    public function testACancellationTellsTheCustomerUnlessTheSystemDidIt(): void
    {
        [$byTheCustomer] = $this->aSubscription();
        [$byTheSystem] = $this->aSubscription();

        $this->getService(SubscriptionLifecycle::class)->cancel($byTheCustomer, ActorType::Customer, (int) $byTheCustomer->getCustomerId());
        $this->getService(SubscriptionLifecycle::class)->cancel($byTheSystem, ActorType::System);

        self::assertSame(['subscription_cancelled'], array_column($this->recorder->log, 'code'));
        self::assertSame('customer', $this->recorder->log[0]['variables']['actor_type']);
        self::assertSame($byTheCustomer->getRef(), $this->recorder->log[0]['variables']['subscription_ref']);
        self::assertNotNull($this->recorder->log[0]['variables']['cancel_at_date']);
    }

    /**
     * The parser of the mailer keeps what it was assigned: a mail that leaves a variable out shows the
     * one of the previous mail of the run. Every variable a message may use is passed, null when absent.
     */
    public function testAMailWithoutAnOrderPassesNullNotNothingSoThePreviousOrderIsNotKept(): void
    {
        [$subscription] = $this->aSubscription();
        RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::Declined;

        $this->moveTo('2028-02-29 10:00:00');
        $this->biller()->bill((int) $subscription->getId());
        $subscription->reload();
        $this->getService(SubscriptionLifecycle::class)->cancel($subscription, ActorType::Customer, (int) $subscription->getCustomerId());

        self::assertSame(['subscription_payment_failed', 'subscription_cancelled'], array_column($this->recorder->log, 'code'));
        [$failed, $cancelled] = array_column($this->recorder->log, 'variables');
        self::assertNotNull($failed['order_ref'], 'The failed installment has an order.');

        foreach (['order_id', 'order_ref', 'installment_number', 'failure_count', 'failure_kind', 'retry_date', 'recipient'] as $name) {
            self::assertArrayHasKey($name, $cancelled, $name.' is passed.');
            self::assertNull($cancelled[$name], $name.' of the cancellation is null, not the one of the previous mail.');
        }

        self::assertSame('customer', $cancelled['actor_type']);
        self::assertSame((int) $subscription->getCustomerId(), $cancelled['customer_id']);
    }
}
