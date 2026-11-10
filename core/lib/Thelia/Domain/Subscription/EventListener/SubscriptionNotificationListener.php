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

namespace Thelia\Domain\Subscription\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\Event\Subscription\SubscriptionInstallmentEvent;
use Thelia\Core\Event\Subscription\SubscriptionStatusChangeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Subscription\Enum\ActorType;
use Thelia\Domain\Subscription\Enum\RecurringChargeOutcome;
use Thelia\Domain\Subscription\Enum\SubscriptionStatus;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Subscription;

/**
 * Tells the customer, and on a suspension the shop, what happens to a subscription.
 *
 *  - subscription_payment_failed: the customer, when a charge is declined or asks the issuer
 *    for an authentication and the subscription goes on retrying. Not when the provider
 *    could not be reached (nothing the customer can do), nor when it is suspended (the next
 *    message says it);
 *  - subscription_suspended: the customer and the shop managers (the variable `recipient` is
 *    `customer` or `shop`), when the retries ran out;
 *  - subscription_cancelled: the customer, when the subscription is cancelled.
 *
 * Sent in the language of the subscription, with a request of its own when the job has none
 * (a command, a worker): the layouts of the mails read one. A mail that cannot leave is logged
 * and never fails the billing that raised the event.
 *
 * Variables of every message: subscription_id, subscription_ref, subscription_status,
 * customer_id, product_title, quantity, unit_price, currency_code, period_unit, period_count,
 * payment_method_label, next_billing_date, current_period_end_date, cancel_at_date (dates as
 * Y-m-d, null when there is none), and order_id and order_ref of the installment when there is one.
 */
final readonly class SubscriptionNotificationListener
{
    public const string PAYMENT_FAILED = 'subscription_payment_failed';
    public const string SUSPENDED = 'subscription_suspended';
    public const string CANCELLED = 'subscription_cancelled';

    public function __construct(
        private MailerFactory $mailer,
        private RequestStack $requestStack,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::SUBSCRIPTION_INSTALLMENT_FAILED)]
    public function tellTheCustomerThePaymentFailed(SubscriptionInstallmentEvent $event): void
    {
        $subscription = $event->getSubscription();
        $outcome = $event->getResult()->outcome;

        if (RecurringChargeOutcome::Retryable === $outcome || SubscriptionStatus::PaymentFailed->value !== $subscription->getStatus()) {
            return;
        }

        $installment = $event->getInstallment();

        $this->send($subscription, self::PAYMENT_FAILED, $this->variablesOf($subscription, $event) + [
            'installment_number' => (int) $installment->getInstallmentNumber(),
            'failure_count' => (int) $subscription->getFailureCount(),
            'failure_kind' => RecurringChargeOutcome::ActionRequired === $outcome ? 'action_required' : 'declined',
            'retry_date' => self::day($subscription->getNextRetryAt()),
        ]);
    }

    #[AsEventListener(event: TheliaEvents::SUBSCRIPTION_STATUS_CHANGE)]
    public function tellWhoMustKnowTheStatusChanged(SubscriptionStatusChangeEvent $event): void
    {
        $subscription = $event->getSubscription();

        if (SubscriptionStatus::Suspended === $event->getNewStatus()) {
            $variables = $this->variablesOf($subscription) + ['failure_count' => (int) $subscription->getFailureCount()];
            $this->send($subscription, self::SUSPENDED, $variables + ['recipient' => 'customer']);
            $this->send($subscription, self::SUSPENDED, $variables + ['recipient' => 'shop'], toTheShop: true);

            return;
        }

        // A cancellation by the system is the erasure of the customer: nobody to tell.
        if (SubscriptionStatus::Cancelled === $event->getNewStatus() && ActorType::System !== $event->getActorType()) {
            $this->send($subscription, self::CANCELLED, $this->variablesOf($subscription) + ['actor_type' => $event->getActorType()->value]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function variablesOf(Subscription $subscription, ?SubscriptionInstallmentEvent $event = null): array
    {
        $product = $subscription->getProductSaleElements()?->getProduct();
        $order = $event?->getOrder();
        $locale = self::localeOf($subscription);

        return [
            'subscription_id' => (int) $subscription->getId(),
            'subscription_ref' => (string) $subscription->getRef(),
            'subscription_status' => (string) $subscription->getStatus(),
            'product_title' => null === $product ? '' : (string) $product->setLocale((string) $locale)->getTitle(),
            'quantity' => (int) $subscription->getQuantity(),
            'unit_price' => (string) $subscription->getUnitPrice(),
            'currency_code' => (string) CurrencyQuery::create()->findPk($subscription->getCurrencyId())?->getCode(),
            'period_unit' => (string) $subscription->getPeriodUnit(),
            'period_count' => (int) $subscription->getPeriodCount(),
            'payment_method_label' => $subscription->getCustomerPaymentMethod()?->getLabel(),
            'next_billing_date' => self::day($subscription->getNextBillingAt()),
            'current_period_end_date' => self::day($subscription->getCurrentPeriodEnd()),
            'cancel_at_date' => self::day($subscription->getCancelAt()),
        ] + (null === $order ? [] : ['order_id' => (int) $order->getId(), 'order_ref' => (string) $order->getRef()]);
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function send(Subscription $subscription, string $messageCode, array $variables, bool $toTheShop = false): void
    {
        $customer = $subscription->getCustomer();
        $locale = self::localeOf($subscription);
        $pushed = $this->giveTheJobARequest();

        try {
            if ($toTheShop) {
                $this->mailer->sendEmailToShopManagersOrFail($messageCode, $variables);

                return;
            }

            $this->mailer->sendEmailMessageOrFail(
                $messageCode,
                [ConfigQuery::getStoreEmail() => ConfigQuery::getStoreName()],
                [$customer->getEmail() => $customer->getFirstname().' '.$customer->getLastname()],
                $variables + ['customer_id' => (int) $customer->getId()],
                $locale,
            );
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->error(\sprintf('The message %s of the subscription %s was not sent: %s', $messageCode, (string) $subscription->getRef(), $throwable->getMessage()));
        } finally {
            if ($pushed) {
                $this->requestStack->pop();
            }
        }
    }

    /**
     * A command or a job has no request, and the layout of a mail reads one: a request on the
     * address of the shop, with an empty session, lives for the time of the send.
     */
    private function giveTheJobARequest(): bool
    {
        if (null !== $this->requestStack->getMainRequest()) {
            return false;
        }

        $address = rtrim((string) (ConfigQuery::read('url_site') ?: 'http://localhost'), '/').'/';
        $request = Request::create($address);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack->push($request);

        return true;
    }

    private static function localeOf(Subscription $subscription): ?string
    {
        return LangQuery::create()->findPk($subscription->getLangId())?->getLocale();
    }

    private static function day(?\DateTimeInterface $moment): ?string
    {
        return $moment?->format('Y-m-d');
    }
}
