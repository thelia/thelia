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

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Thelia\Api\EventListener\ProductPriceCurrencyListener;
use Thelia\Core\Cache\ConfigCacheService;
use Thelia\Core\EventListener\ActiveLangsCacheListener;
use Thelia\Core\EventListener\DefaultCountryCacheListener;
use Thelia\Core\EventListener\ModuleConfigCacheListener;
use Thelia\Core\Routing\Rewriting\RewritingUrlMemoizer;
use Thelia\Core\Translation\Translator;
use Thelia\Messenger\EventListener\WorkerStateResetListener;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Messenger\ProbeMessage;

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

    protected function tearDown(): void
    {
        // The shared entry may now hold what this test wrote: it goes with it.
        $this->getService(ConfigCacheService::class)->initCacheConfigs(true);
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    private function receiveAJob(): void
    {
        $this->getService(WorkerStateResetListener::class)
            ->onWorkerMessageReceived(new WorkerMessageReceivedEvent(new Envelope(new ProbeMessage('next job')), 'async'));
    }
}
