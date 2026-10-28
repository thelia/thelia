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

namespace Thelia\Tests\Integration\Domain\Ai\Tool;

use Psr\Log\AbstractLogger;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiToolCall;
use Thelia\Domain\Ai\Tool\AiToolApprovalStatus;
use Thelia\Domain\Ai\Tool\AiToolCallStatus;
use Thelia\Domain\Ai\Tool\AiToolContext;
use Thelia\Domain\Ai\Tool\AiToolResult;
use Thelia\Domain\Ai\Tool\Builtin\SearchProductsTool;
use Thelia\Domain\Ai\Tool\Builtin\UpdateProductPriceTool;
use Thelia\Model\Admin;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Tests\Support\Ai\AiToolTestCase;

/**
 * The two tools of the core, run through the registry: a read, and a write that waits for approval.
 */
final class AiToolBuiltinToolsTest extends AiToolTestCase
{
    private Product $product;

    private ProductSaleElements $pse;

    private Admin $proposer;

    private Admin $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildServices(['thelia_search_products' => SearchProductsTool::class, 'thelia_update_product_price' => UpdateProductPriceTool::class]);
        $factory = $this->createFixtureFactory();
        $currency = CurrencyQuery::create()->filterByByDefault(true)->findOne() ?? $factory->currency();
        $this->product = $factory->product($factory->category(), $factory->taxRule(), $currency, ['ref' => 'MUG-THELIA', 'title' => 'Mug Thelia', 'basePrice' => 16.5, 'baseQuantity' => 12]);
        $this->pse = $this->product->getDefaultSaleElements();
        $this->proposer = $factory->admin();
        $this->approver = $factory->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW, AccessManager::UPDATE]]);
    }

    public function testTheSearchFindsAProductByReferenceOrByTitleWithItsVariants(): void
    {
        $currency = CurrencyQuery::create()->filterByByDefault(true)->findOne();

        foreach (['MUG-THEL', 'mug thel'] as $query) {
            $result = $this->call('thelia_search_products', ['query' => $query]);

            self::assertSame(AiToolCallStatus::Succeeded, $result->status, $query);
            self::assertSame(1, $result->content['count'], $query);
            $found = $result->content['products'][0];
            self::assertSame($this->product->getId(), $found['id']);
            self::assertSame('MUG-THELIA', $found['ref']);
            self::assertSame('Mug Thelia', $found['title']);
            self::assertSame($this->pse->getId(), $found['variants'][0]['pse_id']);
            self::assertSame('16.500000', $found['variants'][0]['price']);
            self::assertSame($currency?->getCode(), $found['variants'][0]['currency']);
            self::assertSame(12.0, $found['variants'][0]['stock']);
        }
    }

    public function testTheSearchTreatsWildcardsAsText(): void
    {
        self::assertSame(0, $this->call('thelia_search_products', ['query' => '%%'])->content['count']);
        self::assertSame(0, $this->call('thelia_search_products', ['query' => '__'])->content['count']);
    }

    public function testTheSearchHonoursItsLimit(): void
    {
        $factory = $this->createFixtureFactory();
        $currency = CurrencyQuery::create()->filterByByDefault(true)->findOne() ?? $factory->currency();

        foreach (['MUG-B', 'MUG-C'] as $ref) {
            $factory->product($factory->category(), $factory->taxRule(), $currency, ['ref' => $ref]);
        }

        self::assertSame(2, $this->call('thelia_search_products', ['query' => 'MUG-', 'limit' => 2])->content['count']);
        self::assertSame(AiToolCallStatus::InvalidArguments, $this->call('thelia_search_products', ['query' => 'MUG-', 'limit' => 21])->status);
    }

    public function testTheSearchByTitleNeverLoadsMoreThanItsLimit(): void
    {
        $factory = $this->createFixtureFactory();
        $currency = CurrencyQuery::create()->filterByByDefault(true)->findOne() ?? $factory->currency();

        foreach (['A', 'B', 'C', 'D'] as $suffix) {
            $factory->product($factory->category(), $factory->taxRule(), $currency, ['ref' => 'REF-'.$suffix, 'title' => 'Kettle '.$suffix]);
        }

        $connection = $this->getPropelConnection();
        $previous = $connection->getLogger();
        $statements = new class extends AbstractLogger {
            /** @var list<string> */
            public array $lines = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->lines[] = (string) $message;
            }
        };
        $connection->setLogger($statements);
        $connection->useDebug(true);

        try {
            $result = $this->call('thelia_search_products', ['query' => 'kettle', 'limit' => 2]);
        } finally {
            $connection->useDebug(false);
            $connection->setLogger($previous);
        }

        $titleQueries = array_filter($statements->lines, static fn (string $line): bool => str_contains($line, 'product_i18n') && str_contains($line, 'LIKE'));

        self::assertSame(['REF-A', 'REF-B'], array_column($result->content['products'], 'ref'));
        self::assertNotSame([], $titleQueries);

        foreach ($titleQueries as $query) {
            self::assertStringContainsString('LIMIT 2', $query);
        }
    }

    public function testThePriceChangesOnlyOnceAnAdministratorApproves(): void
    {
        $result = $this->call('thelia_update_product_price', ['pse_id' => $this->pse->getId(), 'price' => '14.90']);

        self::assertSame(AiToolCallStatus::PendingApproval, $result->status);
        self::assertSame('16.500000', $this->storedPrice());
        self::assertStringStartsWith('Price of '.$this->pse->getRef().': 16.500000 → 14.900000 ', $result->content['summary']);
        self::assertSame(12.0, ProductSaleElementsQuery::create()->findPk($this->pse->getId())?->getQuantity());

        $this->logInAsAdmin($this->approver);
        $view = $this->approvals->approve((int) $result->approvalId);

        self::assertSame(AiToolApprovalStatus::Applied, $view->status);
        self::assertSame('14.900000', $this->storedPrice());
        $stored = ProductSaleElementsQuery::create()->findPk($this->pse->getId());
        self::assertSame(12.0, $stored?->getQuantity());
        self::assertSame($this->pse->getRef(), $stored?->getRef());
    }

    public function testTheAmendedPriceIsTheOneThatApplies(): void
    {
        $result = $this->call('thelia_update_product_price', ['pse_id' => $this->pse->getId(), 'price' => '14.90']);
        $this->logInAsAdmin($this->approver);

        $view = $this->approvals->approve((int) $result->approvalId, ['pse_id' => $this->pse->getId(), 'price' => '14.5']);

        self::assertTrue($view->amended);
        self::assertSame('14.500000', $this->storedPrice());
        self::assertSame(['pse_id' => $this->pse->getId(), 'price' => '14.90'], $view->originalArguments);
    }

    public function testAPriceChangedAfterTheProposalIsNeverOverwritten(): void
    {
        $result = $this->call('thelia_update_product_price', ['pse_id' => $this->pse->getId(), 'price' => '14.90']);
        $currency = CurrencyQuery::create()->filterByByDefault(true)->findOne();
        ProductPriceQuery::create()->filterByProductSaleElementsId($this->pse->getId())->filterByCurrencyId($currency?->getId())->update(['Price' => '18.000000']);
        $this->logInAsAdmin($this->approver);

        $view = $this->approvals->approve((int) $result->approvalId);

        self::assertSame(AiToolApprovalStatus::Failed, $view->status);
        self::assertSame('The price changed since the proposal.', $view->error);
        self::assertSame('18.000000', $this->storedPrice());
    }

    public function testAVariantThatDoesNotExistIsRefusedAtTheProposal(): void
    {
        $result = $this->call('thelia_update_product_price', ['pse_id' => 2000000000, 'price' => '14.90']);

        self::assertSame(AiToolCallStatus::Failed, $result->status);
        self::assertSame(['error' => 'failed', 'message' => 'This variant does not exist.'], $result->content);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPrices(): iterable
    {
        yield 'a comma' => ['14,90'];
        yield 'a sign' => ['-1'];
        yield 'an exponent' => ['1e3'];
        yield 'too many decimals' => ['1.1234567'];
        yield 'too many digits' => ['12345678901'];
        yield 'empty' => [''];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidPrices')]
    public function testAPriceThatIsNotADecimalStringIsRefused(string $price): void
    {
        $result = $this->call('thelia_update_product_price', ['pse_id' => $this->pse->getId(), 'price' => $price]);

        self::assertSame(AiToolCallStatus::InvalidArguments, $result->status);
    }

    public function testAFloatPriceIsRefused(): void
    {
        self::assertSame(AiToolCallStatus::InvalidArguments, $this->call('thelia_update_product_price', ['pse_id' => $this->pse->getId(), 'price' => 14.9])->status);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function call(string $tool, array $arguments): AiToolResult
    {
        $context = new AiToolContext(AiActor::admin($this->proposer->getId()), 'aiprobe.tools_admin', 'aiprobe.conversation.'.bin2hex(random_bytes(4)));
        $offer = $this->registry->offer($context, [$tool]);

        return $this->registry->call(new AiToolCall('toolu_01', $tool, $arguments), $offer->context);
    }

    private function storedPrice(): string
    {
        $currency = CurrencyQuery::create()->filterByByDefault(true)->findOne();
        \Thelia\Model\Map\ProductPriceTableMap::clearInstancePool();

        return (string) ProductPriceQuery::create()->filterByProductSaleElementsId($this->pse->getId())->filterByCurrencyId($currency?->getId())->findOne()?->getPrice();
    }
}
