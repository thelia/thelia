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

namespace Thelia\Tests\Integration\Api;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Thelia\Api\Bridge\Propel\Event\ResourcePersistedEvent;
use Thelia\Api\Resource\Category as CategoryResource;
use Thelia\Api\Service\API\ResourceCache;
use Thelia\Api\Service\API\ResourceCacheInvalidationListener;
use Thelia\Model\Currency;
use Thelia\Model\CustomerGroupCategory;
use Thelia\Model\CustomerGroupPaymentModule;
use Thelia\Model\ModuleQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The cross-request cache of the data access layer is switched on, with the real event
 * dispatcher of the kernel: a write to what a customer group sees and pays is heard by the
 * listener the container wires, whoever made it, and the next read is computed again once
 * the request that wrote has ended: the pool is not flushed while the write may still be
 * uncommitted, where a reader could refill it from the rows as they were.
 */
final class ResourceCacheCustomerGroupInvalidationTest extends IntegrationTestCase
{
    private const PATH = '/api/front/countries';

    private FixtureFactory $factory;

    private int $computed = 0;

    protected function setUp(): void
    {
        // Read when the cache service is built, which is after the kernel booted.
        putenv('THELIA_DATA_ACCESS_CACHE=1');
        $_ENV['THELIA_DATA_ACCESS_CACHE'] = $_SERVER['THELIA_DATA_ACCESS_CACHE'] = '1';

        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->getService(ResourceCache::class)->clear();
    }

    protected function tearDown(): void
    {
        $this->getService(ResourceCache::class)->clear();
        putenv('THELIA_DATA_ACCESS_CACHE');
        unset($_ENV['THELIA_DATA_ACCESS_CACHE'], $_SERVER['THELIA_DATA_ACCESS_CACHE']);

        parent::tearDown();
    }

    public function testTheCacheKeepsItsEntryWhileNothingWritesToAGroup(): void
    {
        $this->warm();
        $this->factory->taxRule();

        $this->read();

        self::assertSame(0, $this->computed, 'a write to a table the cache does not follow leaves the entry: the control of the tests below');
    }

    public function testASavedGroupIsHeard(): void
    {
        $this->warm();

        $this->factory->customerGroup();

        $this->assertComputedAgain();
    }

    public function testADiscountChangedOnAGroupIsHeard(): void
    {
        $group = $this->factory->customerGroup();
        $this->warm();

        $group->setDiscount('25.000000')->save($this->getPropelConnection());

        $this->assertComputedAgain();
    }

    public function testADeletedGroupIsHeard(): void
    {
        $group = $this->factory->customerGroup();
        $this->warm();

        $group->delete($this->getPropelConnection());

        $this->assertComputedAgain();
    }

    public function testACategoryClosedToAGroupIsHeardAndSoIsItsReopening(): void
    {
        $group = $this->factory->customerGroup();
        $category = $this->factory->category();
        $this->warm();

        $link = (new CustomerGroupCategory())->setCustomerGroupId($group->getId())->setCategoryId($category->getId());
        $link->save($this->getPropelConnection());
        $this->assertComputedAgain('closing');

        $this->warm();
        $link->delete($this->getPropelConnection());
        $this->assertComputedAgain('reopening');
    }

    /**
     * The API removes a category without the CATEGORY_DELETE event of the back office, and the
     * deletion cascades on the lists of the groups without any event of theirs: the event the
     * remove processor dispatches is the only thing the cache hears, once the row is gone.
     */
    public function testACategoryDeletedThroughTheApiIsHeard(): void
    {
        $category = $this->factory->category();
        $this->warm();

        $category->delete($this->getPropelConnection());
        static::getContainer()->get(EventDispatcherInterface::class)->dispatch(
            new ResourcePersistedEvent(CategoryResource::class, $category, ResourcePersistedEvent::OPERATION_DELETE),
        );

        $this->assertComputedAgain();
    }

    public function testAPriceOfAGroupIsHeardAndSoIsItsRemoval(): void
    {
        $group = $this->factory->customerGroup();
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), Currency::getDefaultCurrency(), ['basePrice' => 100.0]);
        $this->warm();

        $price = $this->factory->customerGroupProductPrice($group, $product->getDefaultSaleElements(), Currency::getDefaultCurrency(), 42.0);
        $this->assertComputedAgain('writing');

        $this->warm();
        $price->delete($this->getPropelConnection());
        $this->assertComputedAgain('removing');
    }

    public function testAPaymentModuleOfAGroupIsHeardAndSoIsItsRemoval(): void
    {
        $group = $this->factory->customerGroup();
        $module = ModuleQuery::create()->findOneByCode('Cheque') ?? throw new \RuntimeException('No payment module installed: run bin/test-prepare.');
        $this->warm();

        $link = (new CustomerGroupPaymentModule())->setCustomerGroupId($group->getId())->setModuleId($module->getId());
        $link->save($this->getPropelConnection());
        $this->assertComputedAgain('linking');

        $this->warm();
        $link->delete($this->getPropelConnection());
        $this->assertComputedAgain('unlinking');
    }

    private function warm(): void
    {
        $this->read();
        $this->computed = 0;

        $this->read();
        self::assertSame(0, $this->computed, 'the entry is in the cache: the read is served from it');
    }

    public function testThePoolIsFlushedWhenTheRequestEndsAndNotWhenTheWriteIsMade(): void
    {
        $group = $this->factory->customerGroup();
        $this->warm();

        $group->setDiscount('25.000000')->save($this->getPropelConnection());

        $this->read();
        self::assertSame(0, $this->computed, 'the write only marks the pool: a reader meanwhile is served what was cached');

        $this->endTheRequest();
        $this->read();
        self::assertSame(1, $this->computed, 'the pool is flushed at the end of the request');
        $this->computed = 0;

        $this->endTheRequest();
        $this->read();
        self::assertSame(0, $this->computed, 'one flush per mark: the end of a request that wrote nothing keeps the cache');
    }

    public function testTheEndOfACommandFlushesThePoolToo(): void
    {
        self::assertSame(['flush', 0], ResourceCacheInvalidationListener::getSubscribedEvents()[ConsoleEvents::TERMINATE]);
        self::assertSame(['flush', 0], ResourceCacheInvalidationListener::getSubscribedEvents()[KernelEvents::TERMINATE]);

        $group = $this->factory->customerGroup();
        $this->warm();
        $group->setDiscount('25.000000')->save($this->getPropelConnection());

        $this->getService(ResourceCacheInvalidationListener::class)->flush();

        $this->assertComputedAgain();
    }

    public function testTheEndOfAMessageHandledByAWorkerFlushesThePoolToo(): void
    {
        // An import run by messenger:consume ends neither a request nor a command: without this the
        // pool would serve the old rows until its TTL.
        foreach ([
            new WorkerMessageHandledEvent(new Envelope(new \stdClass()), 'async_heavy'),
            new WorkerMessageFailedEvent(new Envelope(new \stdClass()), 'async_heavy', new \RuntimeException('the import failed')),
        ] as $endOfTheMessage) {
            self::assertSame(['flush', 0], ResourceCacheInvalidationListener::getSubscribedEvents()[$endOfTheMessage::class]);

            $group = $this->factory->customerGroup();
            $this->warm();
            $group->setDiscount('25.000000')->save($this->getPropelConnection());

            $this->read();
            self::assertSame(0, $this->computed, 'the write only marks the pool');

            // The listener alone: the dispatcher of the kernel would also send a failed message to the retry and failure transports.
            $dispatcher = new EventDispatcher();
            $dispatcher->addSubscriber($this->getService(ResourceCacheInvalidationListener::class));
            $dispatcher->dispatch($endOfTheMessage);
            $this->read();

            self::assertSame(1, $this->computed, 'the pool is flushed once the message is over: '.$endOfTheMessage::class);
            $this->computed = 0;
        }
    }

    private function endTheRequest(): void
    {
        static::getContainer()->get(EventDispatcherInterface::class)->dispatch(
            new TerminateEvent(static::getContainer()->get('kernel'), new Request(), new Response()),
            KernelEvents::TERMINATE,
        );
    }

    private function assertComputedAgain(string $context = ''): void
    {
        $this->endTheRequest();
        $this->read();

        self::assertSame(1, $this->computed, trim('the read after the write is computed again '.$context));
        $this->computed = 0;
    }

    private function read(): void
    {
        $this->getService(ResourceCache::class)->remember('invalidation-key', self::PATH, function (): array {
            ++$this->computed;

            return ['payload'];
        });
    }
}
