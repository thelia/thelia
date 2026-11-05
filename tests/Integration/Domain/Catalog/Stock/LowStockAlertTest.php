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

namespace Thelia\Tests\Integration\Domain\Catalog\Stock;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\NullTransport;
use Thelia\Core\Event\ProductSaleElement\LowStockReachedEvent;
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Core\Event\ProductSaleElement\StockLevelChangedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Catalog\Stock\LowStockAlertMailer;
use Thelia\Domain\Catalog\Stock\LowStockThreshold;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\RecordingMailerFactory;

final class LowStockAlertTest extends ActionIntegrationTestCase
{
    use \Thelia\Test\Trait\RecordsSqlQueries;

    /** @var list<LowStockReachedEvent> */
    private array $signals = [];

    private \Closure $spy;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('low_stock_threshold', '20');
        ConfigQuery::write('store_notification_emails', 'shop@example.com');
        $this->signals = [];
        // Records the signal and stops it before the default e-mail listener.
        $this->spy = function (LowStockReachedEvent $event): void {
            $this->signals[] = $event;
            $event->stopPropagation();
        };
        $this->dispatcher->addListener(TheliaEvents::PRODUCT_SALE_ELEMENTS_LOW_STOCK_REACHED, $this->spy, 1000);
    }

    protected function tearDown(): void
    {
        $this->dispatcher->removeListener(TheliaEvents::PRODUCT_SALE_ELEMENTS_LOW_STOCK_REACHED, $this->spy);
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testCrossingTheGlobalThresholdSignalsOnceThenStaysQuietUntilTheStockClimbsBack(): void
    {
        $pse = $this->pse(22.0);

        $this->change($pse, 22.0, 19.0);
        self::assertCount(1, $this->signals);
        self::assertSame($pse->getId(), $this->signals[0]->getProductSaleElementsId());
        self::assertSame(19.0, $this->signals[0]->getQuantity());
        self::assertSame(20.0, $this->signals[0]->getThreshold());

        $this->change($pse, 19.0, 17.0);
        self::assertCount(1, $this->signals);

        $this->change($pse, 17.0, 25.0);
        self::assertCount(1, $this->signals);

        $this->change($pse, 25.0, 18.0);
        self::assertCount(2, $this->signals);
    }

    public function testLandingExactlyOnTheThresholdIsACrossingButStartingOnItIsNot(): void
    {
        $pse = $this->pse(22.0);

        $this->change($pse, 22.0, 20.0);
        self::assertCount(1, $this->signals);

        $this->change($pse, 20.0, 10.0);
        self::assertCount(1, $this->signals);
    }

    public function testAnUnsetThresholdInheritsTheShopOne(): void
    {
        $pse = $this->pse(30.0);
        $threshold = $this->getService(LowStockThreshold::class);

        self::assertSame(20.0, $threshold->forOne($pse->getId()));

        ConfigQuery::write('low_stock_threshold', '7');
        self::assertSame(7.0, $threshold->forOne($pse->getId()));
    }

    public function testAThresholdOfZeroOnTheShopIsAValue(): void
    {
        ConfigQuery::write('low_stock_threshold', '0');
        $pse = $this->pse(3.0);

        self::assertSame(0.0, $this->getService(LowStockThreshold::class)->forOne($pse->getId()));
    }

    public function testTheThresholdOfTheSaleElementOverridesTheShopOne(): void
    {
        $pse = $this->pse(30.0);
        $pse->setLowStockThreshold(2.0)->save();

        self::assertSame(2.0, $this->getService(LowStockThreshold::class)->forOne($pse->getId()));

        $this->change($pse, 30.0, 15.0);
        self::assertCount(0, $this->signals, 'Under the shop threshold but above its own: no signal.');

        $this->change($pse, 15.0, 2.0);
        self::assertCount(1, $this->signals);
        self::assertSame(2.0, $this->signals[0]->getThreshold());
    }

    public function testTheLookupCostsOneQueryWhateverTheNumberOfSaleElements(): void
    {
        $changes = [];
        for ($i = 0; $i < 10; ++$i) {
            $pse = $this->pse(30.0);
            $changes[] = new StockLevelChange($pse->getId(), 30.0, 10.0);
        }

        $statements = $this->recordSqlQueries(fn () => $this->dispatch(
            new StockLevelChangedEvent($changes),
            TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED,
        ));

        self::assertCount(10, $this->signals);
        self::assertSame(1, self::countSqlQueriesSelectingFrom($statements, 'product_sale_elements'));
    }

    public function testTheAlertIsMailedOnceToTheShopWithTheAgreedVariables(): void
    {
        $pse = $this->pse(30.0);
        $mailer = $this->recordingMailer();

        (new LowStockAlertMailer($mailer))->onLowStockReached(new LowStockReachedEvent($pse->getId(), 19.0, 20.0));

        $sent = $mailer->parametersOfMessagesSent('low_stock_alert');
        self::assertCount(1, $sent);
        $product = $pse->getProduct();
        self::assertSame(
            [
                'pse_id' => $pse->getId(),
                'product_id' => $product->getId(),
                'product_ref' => $product->getRef(),
                'pse_ref' => $pse->getRef(),
                'product_title' => (string) $product->setLocale(\Thelia\Model\Lang::getDefaultLanguage()->getLocale())->getTitle(),
                'quantity' => 19.0,
                'threshold' => 20.0,
            ],
            $sent[0],
        );
    }

    /**
     * The alert as the shop manager reads it, rendered from the installed mail template: the product,
     * the combination, the stock left and the threshold, in the HTML and in the text. The CI of the
     * core installs the template package of its main branch, which may not carry the alert yet.
     */
    public function testTheAlertIsRenderedWithTheProductTheStockAndTheThreshold(): void
    {
        $directory = $this->getService(TemplateHelperInterface::class)->getActiveMailTemplate()->getAbsolutePath();

        foreach (['low_stock_alert.html.twig', 'low_stock_alert.txt.twig'] as $file) {
            if (!is_file($directory.DS.$file)) {
                self::markTestSkipped(\sprintf('The installed mail template (%s) does not carry the low stock alert yet.', $directory));
            }
        }

        $pse = $this->pse(30.0);
        $mailer = $this->recordingMailer();
        (new LowStockAlertMailer($mailer))->onLowStockReached(new LowStockReachedEvent($pse->getId(), 19.0, 20.0));
        $parameters = $mailer->parametersOfMessagesSent('low_stock_alert')[0] ?? null;
        self::assertIsArray($parameters);

        $email = (new MailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
            new NullTransport(),
        ))->createEmailMessage('low_stock_alert', ['sender@example.com' => 'Sender'], ['recipient@example.com' => 'Recipient'], $parameters, 'en_US');

        foreach (['html' => (string) $email->getHtmlBody(), 'text' => (string) $email->getTextBody()] as $part => $body) {
            foreach ([(string) $parameters['product_ref'], (string) $parameters['pse_ref']] as $expected) {
                self::assertStringContainsString($expected, $body, $expected.' in the '.$part.' part.');
            }
        }

        // The figures as the template prints them, with their label: a bare 19 or 20 would be found
        // in any reference, date or style of the mail.
        self::assertMatchesRegularExpression('/^Remaining stock : 19$/m', (string) $email->getTextBody());
        self::assertMatchesRegularExpression('/^Alert threshold : 20$/m', (string) $email->getTextBody());
        self::assertMatchesRegularExpression('#Remaining stock</strong></td>\s*<td[^>]*>19</td>#', (string) $email->getHtmlBody());
        self::assertMatchesRegularExpression('#Alert threshold</strong></td>\s*<td[^>]*>20</td>#', (string) $email->getHtmlBody());
    }

    public function testAFailureToMailIsLoggedAndNotRaised(): void
    {
        $pse = $this->pse(30.0);
        $failing = new class($this->getService(TemplateHelperInterface::class), $this->getService(ParserResolver::class), $this->getService(MailerInterface::class)) extends RecordingMailerFactory {
            public function sendEmailToShopManagers(string $messageCode, array $messageParameters = [], array $replyTo = []): void
            {
                throw new \RuntimeException('smtp down');
            }
        };

        (new LowStockAlertMailer($failing))->onLowStockReached(new LowStockReachedEvent($pse->getId(), 19.0, 20.0));

        $this->addToAssertionCount(1);
    }

    public function testTheDefaultAlertListenerRunsAfterThePriorityOnesSoAModuleCanStopIt(): void
    {
        $listeners = $this->dispatcher->getListeners(TheliaEvents::PRODUCT_SALE_ELEMENTS_LOW_STOCK_REACHED);
        $position = null;
        foreach ($listeners as $index => $listener) {
            if (\is_array($listener) && $listener[0] instanceof LowStockAlertMailer) {
                $position = $index;
            }
        }

        self::assertNotNull($position, 'The default alert listener is registered.');
        self::assertSame(\count($listeners) - 1, $position, 'It is the last one to run.');
        self::assertSame(-128, $this->dispatcher->getListenerPriority(TheliaEvents::PRODUCT_SALE_ELEMENTS_LOW_STOCK_REACHED, $listeners[$position]));
    }

    private function recordingMailer(): RecordingMailerFactory
    {
        return new RecordingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
        );
    }

    private function pse(float $quantity): ProductSaleElements
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $currency);

        return $this->factory->productSaleElement($product, ['quantity' => $quantity]);
    }

    private function change(ProductSaleElements $pse, float $previous, float $new): void
    {
        $this->dispatch(
            new StockLevelChangedEvent([new StockLevelChange($pse->getId(), $previous, $new)]),
            TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED,
        );
    }
}
