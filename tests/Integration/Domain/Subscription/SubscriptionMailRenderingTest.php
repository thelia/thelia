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
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mime\Email;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Subscription\Enum\ActorType;
use Thelia\Domain\Subscription\Enum\RecurringChargeOutcome;
use Thelia\Domain\Subscription\EventListener\SubscriptionNotificationListener;
use Thelia\Domain\Subscription\SubscriptionLifecycle;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\LangQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Payment\RecurringTestPaymentModule;

/**
 * The three messages of a subscription as the customer and the shop read them, rendered by the
 * real mailer from the active e-mail template with the variables the listener sends. The core CI
 * installs the published template package, which may not carry these files yet.
 */
final class SubscriptionMailRenderingTest extends ActionIntegrationTestCase
{
    use SubscriptionScenario;

    private const array FILES = [
        'subscription_payment_failed.html.twig',
        'subscription_payment_failed.txt.twig',
        'subscription_suspended.html.twig',
        'subscription_suspended.txt.twig',
        'subscription_cancelled.html.twig',
        'subscription_cancelled.txt.twig',
    ];

    /** @var list<array{code: string, recipient: string, variables: array<string, mixed>, locale: ?string}> */
    private array $sent = [];

    /** @var list<array{string, \Closure}> */
    private array $listeners = [];

    private MailerFactory $mailer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTheSubscriptionShop();

        $sent = &$this->sent;
        $this->mailer = new class($this->getService(TemplateHelperInterface::class), $this->getService(ParserResolver::class), $this->getService(MailerInterface::class), new NullTransport(), $sent) extends MailerFactory {
            /** @param list<array{code: string, recipient: string, variables: array<string, mixed>, locale: ?string}> $sent */
            public function __construct(TemplateHelperInterface $templateHelper, ParserResolver $parserResolver, MailerInterface $mailer, NullTransport $transport, private array &$sent)
            {
                parent::__construct($templateHelper, $parserResolver, $mailer, $transport);
            }

            public function sendEmailMessageOrFail(string $messageCode, array $from, array $to, array $messageParameters = [], ?string $locale = null, array $cc = [], array $bcc = [], array $replyTo = []): void
            {
                $this->sent[] = ['code' => $messageCode, 'recipient' => 'customer', 'variables' => $messageParameters, 'locale' => $locale];
            }

            public function sendEmailToShopManagersOrFail(string $messageCode, array $messageParameters = [], array $replyTo = []): void
            {
                $this->sent[] = ['code' => $messageCode, 'recipient' => 'shop', 'variables' => $messageParameters, 'locale' => null];
            }
        };

        $listener = new SubscriptionNotificationListener($this->mailer, $this->getService(RequestStack::class));
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

    public function testTheThreeMessagesAreRenderedWithTheSubscriptionAndTheProduct(): void
    {
        $this->skipWithoutTheTemplateFiles(self::FILES);

        $title = 'Rendered coffee box';
        [$subscription, $product] = $this->aSubscription();
        $product->setLocale($this->localeOf($subscription))->setTitle($title)->save();
        RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::Declined;

        foreach (['2028-02-29 10:00:00', '2028-03-02 10:00:00', '2028-03-04 10:00:00'] as $moment) {
            $this->moveTo($moment);
            $this->biller()->bill((int) $subscription->getId());
        }

        [$cancelled, $otherProduct] = $this->aSubscription();
        $otherProduct->setLocale($this->localeOf($cancelled))->setTitle($title)->save();
        $this->getService(SubscriptionLifecycle::class)->cancel($cancelled, ActorType::Customer, (int) $cancelled->getCustomerId());

        self::assertSame(
            ['subscription_payment_failed', 'subscription_payment_failed', 'subscription_suspended', 'subscription_suspended', 'subscription_cancelled'],
            array_column($this->sent, 'code'),
        );

        $subjects = [];

        foreach ($this->sent as $index => $message) {
            $ref = $index < 4 ? $subscription->getRef() : $cancelled->getRef();
            $email = $this->render($message, 'en_US');
            $subjects[$index] = $email->getSubject();

            self::assertStringContainsString((string) $ref, (string) $email->getSubject(), $message['code'].' '.$message['recipient'].' subject.');

            foreach (['html' => (string) $email->getHtmlBody(), 'text' => (string) $email->getTextBody()] as $part => $body) {
                self::assertStringContainsString((string) $ref, $body, $message['code'].' '.$message['recipient'].': the reference in the '.$part.' part.');
                self::assertStringContainsString($title, $body, $message['code'].' '.$message['recipient'].': the product in the '.$part.' part.');
            }
        }

        self::assertStringContainsString('3', (string) $this->render($this->sent[3], 'en_US')->getSubject());
        self::assertNotSame($subjects[2], $subjects[3], 'The shop reads its own subject of the suspension.');
    }

    public function testTheShopAndTheCustomerReadADifferentSubjectOfTheSuspensionInEnglishAndFrench(): void
    {
        $this->skipWithoutTheTemplateFiles(self::FILES);

        [$subscription] = $this->aSubscription();
        RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::Declined;

        foreach (['2028-02-29 10:00:00', '2028-03-02 10:00:00', '2028-03-04 10:00:00'] as $moment) {
            $this->moveTo($moment);
            $this->biller()->bill((int) $subscription->getId());
        }

        [$forTheCustomer, $forTheShop] = \array_slice($this->sent, 2);

        foreach (['en_US', 'fr_FR'] as $locale) {
            $customerSubject = (string) $this->render($forTheCustomer, $locale)->getSubject();
            $shopSubject = (string) $this->render($forTheShop, $locale)->getSubject();

            self::assertNotSame($customerSubject, $shopSubject, $locale);
            self::assertStringContainsString((string) $subscription->getRef(), $customerSubject, $locale);
            self::assertStringContainsString((string) $subscription->getRef(), $shopSubject, $locale);
            self::assertStringContainsString('3', $shopSubject, $locale.': the number of failed charges.');
            self::assertStringNotContainsString('{%', $shopSubject);
        }

        self::assertSame('Subscription '.$subscription->getRef().' suspended after 3 failed charges', $this->render($forTheShop, 'en_US')->getSubject());
        self::assertStringStartsWith('Abonnement '.$subscription->getRef().' suspendu après 3', (string) $this->render($forTheShop, 'fr_FR')->getSubject());
        self::assertStringStartsWith('Your subscription '.$subscription->getRef(), (string) $this->render($forTheCustomer, 'en_US')->getSubject());
        self::assertStringStartsWith('Votre abonnement '.$subscription->getRef(), (string) $this->render($forTheCustomer, 'fr_FR')->getSubject());
    }

    private function localeOf(\Thelia\Model\Subscription $subscription): string
    {
        return (string) LangQuery::create()->findPk($subscription->getLangId())?->getLocale();
    }

    /**
     * @param array{code: string, recipient: string, variables: array<string, mixed>, locale: ?string} $message
     */
    private function render(array $message, string $locale): Email
    {
        self::assertNotNull(LangQuery::create()->findOneByLocale($locale), $locale.' is an active language.');

        return $this->mailer->createEmailMessage(
            $message['code'],
            ['sender@example.com' => 'Sender'],
            ['recipient@example.com' => 'Recipient'],
            $message['variables'],
            $locale,
        );
    }

    /**
     * @param list<string> $files
     */
    private function skipWithoutTheTemplateFiles(array $files): void
    {
        $directory = $this->getService(TemplateHelperInterface::class)->getActiveMailTemplate()->getAbsolutePath();

        foreach ($files as $file) {
            if (!is_file($directory.\DIRECTORY_SEPARATOR.$file)) {
                self::markTestSkipped(\sprintf('The installed mail template (%s) does not carry %s yet.', $directory, $file));
            }
        }
    }
}
