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

namespace Thelia\Tests\Integration\Messenger;

use Propel\Runtime\Propel;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Routing\RequestContext;
use Thelia\Api\EventListener\ProductPriceCurrencyListener;
use Thelia\Core\Cache\ConfigCacheService;
use Thelia\Core\EventListener\ActiveLangsCacheListener;
use Thelia\Core\EventListener\DefaultCountryCacheListener;
use Thelia\Core\EventListener\ModuleConfigCacheListener;
use Thelia\Core\Routing\Rewriting\RewritingUrlMemoizer;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Translation\Translator;
use Thelia\Mailer\MailerFactory;
use Thelia\Messenger\EventListener\WorkerStateResetListener;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Lang;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\TaxRuleCountryQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Messenger\ProbeMessage;
use Thelia\Tools\URL;

/**
 * A worker handles job after job in one process: each of them starts from what a
 * fresh command starts from.
 */
final class WorkerStateResetListenerTest extends IntegrationTestCase
{
    /**
     * What the core forgets when a command starts is what a worker forgets before
     * each job. A core listener added to ConsoleEvents::COMMAND without its
     * counterpart in WorkerStateResetListener fails here.
     */
    public function testEveryCoreListenerOfTheStartOfACommandHasItsCounterpartBeforeEachJob(): void
    {
        $dispatcher = $this->getService(EventDispatcherInterface::class);

        $coreListeners = [];
        foreach ($dispatcher->getListeners(ConsoleEvents::COMMAND) as $listener) {
            $class = \is_array($listener) && \is_object($listener[0]) ? $listener[0]::class : null;

            if (null !== $class && str_starts_with($class, 'Thelia\\')) {
                $coreListeners[] = $class;
            }
        }

        self::assertEqualsCanonicalizing(
            [
                RewritingUrlMemoizer::class,
                ActiveLangsCacheListener::class,
                DefaultCountryCacheListener::class,
                ModuleConfigCacheListener::class,
                ProductPriceCurrencyListener::class,
            ],
            $coreListeners,
            'A core listener of ConsoleEvents::COMMAND was added or removed: reset the same state in WorkerStateResetListener, then update this list.',
        );
    }

    public function testASettingChangedSinceThePreviousJobIsSeenByTheNext(): void
    {
        ConfigQuery::write('store_name', 'The shop as it is now');
        // What a worker started earlier still holds in memory.
        ConfigQuery::initCache(['store_name' => 'The shop as it was']);

        $this->receiveAJob();

        self::assertSame('The shop as it is now', ConfigQuery::read('store_name'));
    }

    public function testADefaultCurrencyChangedSinceThePreviousJobIsSeenByTheNext(): void
    {
        $previous = Currency::getDefaultCurrency();
        $next = CurrencyQuery::create()->filterById($previous->getId(), '<>')->findOne();
        self::assertNotNull($next);

        CurrencyQuery::create()->update(['ByDefault' => 0]);
        CurrencyQuery::create()->filterById($next->getId())->update(['ByDefault' => 1]);

        try {
            $this->receiveAJob();

            self::assertSame($next->getId(), Currency::getDefaultCurrency()->getId());
        } finally {
            Currency::resetDefaultCurrencyCache();
        }
    }

    public function testATaxRuleChangedSinceThePreviousJobIsSeenByTheNext(): void
    {
        $line = TaxRuleCountryQuery::create()->findOne();
        self::assertNotNull($line);
        $taxRule = $line->getTaxRule();
        $country = CountryQuery::create()->findPk($line->getCountryId());
        self::assertNotNull($country);

        self::assertNotEmpty(TaxRuleQuery::create()->getTaxCalculatorCollection($taxRule, $country));

        // The rule no longer taxes that country.
        TaxRuleCountryQuery::create()->filterByTaxRuleId($taxRule->getId())->filterByCountryId($country->getId())->delete();

        try {
            $this->receiveAJob();

            self::assertEmpty(TaxRuleQuery::create()->getTaxCalculatorCollection($taxRule, $country));
        } finally {
            TaxRuleQuery::resetCache();
        }
    }

    public function testAReturnStatusRenamedSinceThePreviousJobIsSeenByTheNext(): void
    {
        $status = OrderReturnStatusQuery::create()->findOne();
        self::assertNotNull($status);
        $code = (string) $status->getCode();

        self::assertSame($status->getId(), OrderReturnStatusQuery::create()->findIdByCode($code));

        OrderReturnStatusQuery::create()->filterById($status->getId())->update(['Code' => $code.'_renamed']);

        try {
            $this->receiveAJob();

            self::assertNull(OrderReturnStatusQuery::create()->findIdByCode($code));
        } finally {
            OrderReturnStatusQuery::resetCache();
        }
    }

    /**
     * Loops do not cache under test: what a worker forgets is read on the class.
     */
    public function testTheResultsOfTheLoopsOfThePreviousJobAreForgotten(): void
    {
        $results = new \ReflectionProperty(BaseLoop::class, 'cacheLoopResult');
        $results->setValue(null, ['a loop of the previous job' => 'its rows']);

        try {
            $this->receiveAJob();

            self::assertSame([], $results->getValue());
        } finally {
            BaseLoop::resetCachedResults();
        }
    }

    public function testTheLanguageOfThePreviousJobIsNotHandedToTheNext(): void
    {
        // A worker has no request: the translator answers with the locale it holds.
        $requestStack = $this->getService(RequestStack::class);
        $request = $requestStack->pop();

        try {
            $translator = $this->getService(Translator::class);
            $defaultLocale = Lang::getDefaultLanguage()->getLocale();
            $otherLocale = 'fr_FR' === $defaultLocale ? 'en_US' : 'fr_FR';
            $translator->setLocale($otherLocale);

            $this->receiveAJob();

            self::assertSame($defaultLocale, $translator->getLocale());
        } finally {
            if (null !== $request) {
                $requestStack->push($request);
            }
        }
    }

    /**
     * A worker has no request, and a job may render a mail whose template runs loops,
     * which read the request of the page: the order confirmation, sent again by a job of
     * a module, renders as it does in the page.
     */
    public function testAJobRendersAMailWhoseTemplateRunsLoops(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order($factory->customer($factory->customerTitle()));
        $requestStack = $this->getService(RequestStack::class);
        $requests = [];

        while (null !== $request = $requestStack->pop()) {
            $requests[] = $request;
        }

        try {
            $this->getService(EventDispatcherInterface::class)->dispatch(new WorkerMessageReceivedEvent(new Envelope(new ProbeMessage('next job')), 'async'));

            $email = $this->getService(MailerFactory::class)->createEmailMessage(
                'order_confirmation',
                ['shop@example.com' => 'Shop'],
                ['buyer@example.com' => 'Buyer'],
                ['order_id' => $order->getId(), 'order_ref' => $order->getRef()],
            );

            self::assertStringContainsString((string) $order->getRef(), (string) $email->getHtmlBody());

            $this->finishTheJob();
            self::assertNull($requestStack->getCurrentRequest(), 'The next job starts without the request of this one.');
        } finally {
            while (null !== $requestStack->pop()) {
            }

            foreach (array_reverse($requests) as $request) {
                $requestStack->push($request);
            }
        }
    }

    /**
     * A shop in a folder of its site (DEFAULT_URI=https://example.com/shop) keeps that
     * folder in the links a job writes.
     */
    public function testTheRequestOfAJobKeepsTheFolderOfTheShop(): void
    {
        $context = $this->getService(RequestContext::class);
        $baseUrl = $context->getBaseUrl();
        $requestStack = $this->getService(RequestStack::class);
        $requests = [];

        while (null !== $request = $requestStack->pop()) {
            $requests[] = $request;
        }

        $context->setBaseUrl('/shop');

        try {
            $this->getService(EventDispatcherInterface::class)->dispatch(new WorkerMessageReceivedEvent(new Envelope(new ProbeMessage('next job')), 'async'));

            self::assertSame('/shop', $requestStack->getMainRequest()?->getBaseUrl());
            self::assertStringEndsWith('/shop', $this->getService(URL::class)->getBaseUrl());
            $this->finishTheJob();

            // At the root of its site, a shop has no folder in its links.
            $context->setBaseUrl('');
            $this->getService(EventDispatcherInterface::class)->dispatch(new WorkerMessageReceivedEvent(new Envelope(new ProbeMessage('next job')), 'async'));

            self::assertSame('', $requestStack->getMainRequest()?->getBaseUrl());
        } finally {
            $this->finishTheJob();
            $context->setBaseUrl($baseUrl);

            while (null !== $requestStack->pop()) {
            }

            foreach (array_reverse($requests) as $request) {
                $requestStack->push($request);
            }
        }
    }

    /**
     * A job the worker skipped (messenger:failed:retry, answered "skip") ends with no
     * event: the next job still starts on a request of its own.
     */
    public function testAJobNeverStartsOnTheRequestOfAnother(): void
    {
        $requestStack = $this->getService(RequestStack::class);
        $requests = [];

        while (null !== $request = $requestStack->pop()) {
            $requests[] = $request;
        }

        try {
            $dispatcher = $this->getService(EventDispatcherInterface::class);
            $dispatcher->dispatch(new WorkerMessageReceivedEvent(new Envelope(new ProbeMessage('skipped job')), 'failed'));
            $skipped = $requestStack->getMainRequest();
            $skipped?->getSession()->set('left_by', 'the skipped job');

            $dispatcher->dispatch(new WorkerMessageReceivedEvent(new Envelope(new ProbeMessage('next job')), 'failed'));

            self::assertNotSame($skipped, $requestStack->getMainRequest());
            self::assertNull($requestStack->getMainRequest()?->getSession()->get('left_by'));
        } finally {
            $this->finishTheJob();

            while (null !== $requestStack->pop()) {
            }

            foreach (array_reverse($requests) as $request) {
                $requestStack->push($request);
            }
        }
    }

    public function testAWorkerReadsEveryModelAgainRatherThanFromThePool(): void
    {
        Propel::enableInstancePooling();

        try {
            $this->getService(WorkerStateResetListener::class)->onWorkerStarted(new WorkerStartedEvent(new Worker([], $this->getService(MessageBusInterface::class))));

            self::assertFalse(Propel::isInstancePoolingEnabled());
        } finally {
            Propel::disableInstancePooling();
        }
    }

    protected function tearDown(): void
    {
        // The shared entry may now hold what this test wrote: it goes with it.
        $this->getService(ConfigCacheService::class)->initCacheConfigs(true);
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    private function finishTheJob(): void
    {
        $this->getService(EventDispatcherInterface::class)->dispatch(new WorkerMessageHandledEvent(new Envelope(new ProbeMessage('next job')), 'async'));
    }

    private function receiveAJob(): void
    {
        $this->getService(WorkerStateResetListener::class)
            ->onWorkerMessageReceived(new WorkerMessageReceivedEvent(new Envelope(new ProbeMessage('next job')), 'async'));
    }
}
