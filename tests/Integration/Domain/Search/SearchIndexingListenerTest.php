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

namespace Thelia\Tests\Integration\Domain\Search;

use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Thelia\Domain\Search\EventListener\SearchIndexingListener;
use Thelia\Domain\Search\Message\IndexDocument;
use Thelia\Domain\Search\Message\RemoveDocument;
use Thelia\Domain\Search\SearchEngineRegistry;
use Thelia\Domain\Search\SearchIndexingQueue;
use Thelia\Model\Product;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Search\InMemorySearchEngine;

final class SearchIndexingListenerTest extends IntegrationTestCase
{
    /** @var list<object> */
    private array $dispatched = [];
    private ?SearchIndexingListener $listener = null;
    private FixtureFactory $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatched = [];
        $this->fixtures = $this->createFixtureFactory();
    }

    protected function tearDown(): void
    {
        if (null !== $this->listener) {
            $this->dispatcher()->removeSubscriber($this->listener);
            $this->listener = null;
        }

        parent::tearDown();
    }

    public function testASavedProductIsQueuedOnlyOnceTheWorkIsOver(): void
    {
        $product = $this->product();
        $listener = $this->listen(withEngine: true);

        $product->setVisible(0)->save();
        $product->setLocale('en_US')->setTitle('Renamed')->save();

        self::assertSame([], $this->dispatched);

        $listener->flush();

        self::assertEquals([new IndexDocument('product', [$product->getId()])], $this->dispatched);
    }

    public function testAProductHiddenWithoutTouchingItsTextIsQueued(): void
    {
        $product = $this->product();
        $listener = $this->listen(withEngine: true);

        $product->setVisible(0)->save();
        $listener->flush();

        self::assertEquals([new IndexDocument('product', [$product->getId()])], $this->dispatched);
    }

    public function testANewProductIsQueued(): void
    {
        $listener = $this->listen(withEngine: true);

        $product = $this->product();
        $listener->flush();

        self::assertContainsEquals(new IndexDocument('product', [$product->getId()]), $this->dispatched);
    }

    public function testAProductSavedWithoutAnIndexedChangeIsNotQueued(): void
    {
        $product = $this->product();
        $listener = $this->listen(withEngine: true);

        $product->setVirtual(1)->save();
        $listener->flush();

        self::assertSame([], $this->dispatched);
    }

    public function testAStockWrittenOnASaleElementQueuesNothing(): void
    {
        $product = $this->product();
        $listener = $this->listen(withEngine: true);

        $product->getDefaultSaleElements()->setQuantity(42)->save();
        $listener->flush();

        self::assertSame([], $this->dispatched);
    }

    public function testSavingTheRowOfABrandDoesNotQueueItsProducts(): void
    {
        $brand = $this->fixtures->brand();
        $product = $this->product();
        $product->setBrandId($brand->getId())->save();
        $listener = $this->listen(withEngine: true);

        $brand->setVisible(0)->save();
        $listener->flush();

        self::assertEquals([new IndexDocument('brand', [$brand->getId()])], $this->dispatched);
    }

    public function testRenamingABrandQueuesTheProductsThatShowItsName(): void
    {
        $brand = $this->fixtures->brand();
        $product = $this->product();
        $product->setBrandId($brand->getId())->save();
        $listener = $this->listen(withEngine: true);

        $brand->setLocale('en_US')->setTitle('Renamed brand')->save();
        $listener->flush();

        self::assertContainsEquals(new IndexDocument('product', [$product->getId()]), $this->dispatched);
    }

    public function testAContentHiddenWithoutTouchingItsTextIsQueued(): void
    {
        $content = $this->fixtures->content($this->fixtures->folder());
        $listener = $this->listen(withEngine: true);

        $content->setVisible(0)->save();
        $listener->flush();

        self::assertEquals([new IndexDocument('content', [$content->getId()])], $this->dispatched);
    }

    public function testADeletedProductIsQueuedForRemoval(): void
    {
        $product = $this->product();
        $id = $product->getId();
        $listener = $this->listen(withEngine: true);

        $product->delete();
        $listener->flush();

        self::assertEquals([new RemoveDocument('product', [$id])], $this->dispatched);
    }

    public function testAProductSavedThenDeletedInTheSameWorkIsOnlyRemoved(): void
    {
        $product = $this->product();
        $id = $product->getId();
        $listener = $this->listen(withEngine: true);

        $product->setLocale('en_US')->setTitle('Renamed')->save();
        $product->delete();
        $listener->flush();

        self::assertEquals([new RemoveDocument('product', [$id])], $this->dispatched);
    }

    public function testADeletedTranslationReindexesItsProduct(): void
    {
        $product = $this->product();
        $listener = $this->listen(withEngine: true);

        ProductI18nQuery::create()->filterById($product->getId())->filterByLocale('en_US')->findOne()?->delete();
        $listener->flush();

        self::assertEquals([new IndexDocument('product', [$product->getId()])], $this->dispatched);
    }

    public function testASubcategoryDeletedWithItsParentIsQueued(): void
    {
        $parent = $this->fixtures->category();
        $child = $this->fixtures->category(['parent' => $parent->getId()]);
        $listener = $this->listen(withEngine: true);

        $parent->delete();
        $listener->flush();

        self::assertEquals([new RemoveDocument('category', [$parent->getId(), $child->getId()])], $this->dispatched);
    }

    public function testDeletingABrandQueuesItAndTheProductsThatShowedItsName(): void
    {
        $brand = $this->fixtures->brand();
        $product = $this->product();
        $product->setBrandId($brand->getId())->save();
        $listener = $this->listen(withEngine: true);

        $brand->delete();
        $listener->flush();

        self::assertEqualsCanonicalizing([
            new RemoveDocument('brand', [$brand->getId()]),
            new IndexDocument('product', [$product->getId()]),
        ], $this->dispatched);
    }

    public function testAnEanCodeWrittenStraightOnASaleElementQueuesItsProduct(): void
    {
        $product = $this->product();
        $listener = $this->listen(withEngine: true);

        $product->getDefaultSaleElements()->setEanCode('4006381333931')->save();
        $listener->flush();

        self::assertEquals([new IndexDocument('product', [$product->getId()])], $this->dispatched);
    }

    public function testNothingIsQueuedWithoutAnEngine(): void
    {
        $product = $this->product();
        $listener = $this->listen(withEngine: false);

        $product->setVisible(0)->save();
        $listener->flush();

        self::assertSame([], $this->dispatched);
    }

    public function testAQueueThatRefusesTheMessageDoesNotBreakTheWrite(): void
    {
        $product = $this->product();
        $queue = new SearchIndexingQueue(new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                throw new \RuntimeException('Transport down');
            }
        }, new NullLogger());
        $queue->add('product', $product->getId());

        $queue->flush();

        $this->expectNotToPerformAssertions();
    }

    public function testAnEngineThatCannotBeBuiltLetsTheWriteThrough(): void
    {
        $product = $this->product();
        $this->listener = new SearchIndexingListener(
            new SearchEngineRegistry((static function (): iterable {
                throw new \RuntimeException('Missing API key');
                yield new InMemorySearchEngine();
            })()),
            new SearchIndexingQueue($this->bus(), new NullLogger()),
            new NullLogger(),
        );
        $this->dispatcher()->addSubscriber($this->listener);

        $product->setRef('RENAMED-'.$product->getId())->save();

        self::assertSame('RENAMED-'.$product->getId(), ProductQuery::create()->filterById($product->getId())->select(['Ref'])->findOne());
    }

    public function testAJobHandledByTheWorkerSendsWhatItChanged(): void
    {
        $product = $this->product();
        $this->listen(withEngine: true);

        $product->setRef('HANDLED-'.$product->getId())->save();
        $this->dispatcher()->dispatch(new WorkerMessageHandledEvent(new Envelope(new \stdClass()), 'async'));

        self::assertEquals([new IndexDocument('product', [$product->getId()])], $this->dispatched);
    }

    public function testAJobFailingInlineIsLoggedWithWhatTheHandlerThrew(): void
    {
        $context = [];
        $queue = new SearchIndexingQueue(new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                throw new HandlerFailedException(new Envelope($message), [new \DomainException('Engine refused the document')]);
            }
        }, new class($context) extends AbstractLogger {
            /** @param array<string, mixed> $context */
            public function __construct(private array &$context)
            {
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->context = $context;
            }
        });
        $queue->add('product', 1);

        $queue->flush();

        self::assertSame(\DomainException::class, $context['exception'] ?? null);
    }

    private function listen(bool $withEngine): SearchIndexingListener
    {
        $this->listener = new SearchIndexingListener(
            new SearchEngineRegistry($withEngine ? [new InMemorySearchEngine()] : []),
            new SearchIndexingQueue($this->bus(), new NullLogger()),
            new NullLogger(),
        );
        $this->dispatcher()->addSubscriber($this->listener);

        return $this->listener;
    }

    private function bus(): MessageBusInterface
    {
        $dispatched = &$this->dispatched;

        return new class($dispatched) implements MessageBusInterface {
            /** @param list<object> $dispatched */
            public function __construct(private array &$dispatched)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;

                return new Envelope($message);
            }
        };
    }

    private function product(): Product
    {
        return $this->fixtures->product($this->fixtures->category(), $this->fixtures->taxRule(), $this->fixtures->currency(), ['title' => 'Garden chair']);
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }
}
