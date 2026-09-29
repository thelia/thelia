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

namespace Thelia\Tests\Integration\Domain\QuickOrder;

use Thelia\Domain\Catalog\DTO\ReferenceQuantity;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\QuickOrder\DTO\Candidate;
use Thelia\Domain\QuickOrder\DTO\QuickOrderLine;
use Thelia\Domain\QuickOrder\Enum\LineStatus;
use Thelia\Domain\QuickOrder\Service\ReferenceResolver;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

final class ReferenceResolverTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private FixtureFactory $factory;

    private ReferenceResolver $resolver;

    private Currency $currency;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->resolver = $this->getService(ReferenceResolver::class);
        $this->currency = $this->factory->currency();
        $this->customer = $this->factory->customer($this->factory->customerTitle());
    }

    public function testASaleElementReferenceResolvesWithItsTitleAndPrice(): void
    {
        $product = $this->product(['title' => 'Nitrile gloves', 'basePrice' => 12.5]);
        $saleElements = $this->defaultSaleElementsOf($product);

        $line = $this->resolveOne((string) $saleElements->getRef(), 3);

        self::assertSame(LineStatus::Resolved, $line->status);
        self::assertSame((int) $saleElements->getId(), $line->productSaleElementsId);
        self::assertSame((int) $product->getId(), $line->productId);
        self::assertSame('Nitrile gloves', $line->title);
        self::assertEqualsWithDelta(12.5, $line->untaxedUnitPrice, 0.0001);
        self::assertSame(3, $line->quantity);
    }

    public function testTheCaseOfTheReferenceDoesNotMatter(): void
    {
        $saleElements = $this->defaultSaleElementsOf($this->product(['ref' => 'GLOVE-NITRILE-M']));

        self::assertSame((int) $saleElements->getId(), $this->resolveOne('glove-nitrile-m')->productSaleElementsId);
    }

    public function testAnEanCodeResolvesLikeAReference(): void
    {
        $saleElements = $this->defaultSaleElementsOf($this->product());
        $saleElements->setEanCode('3760123450012')->save($this->getPropelConnection());

        self::assertSame((int) $saleElements->getId(), $this->resolveOne('3760123450012')->productSaleElementsId);
    }

    public function testAProductReferenceNoSaleElementCarriesResolvesToTheDefaultSaleElement(): void
    {
        $product = $this->product(['ref' => 'BOOTS-01']);
        $default = $this->defaultSaleElementsOf($product);
        $default->setRef('BOOTS-01-42')->save($this->getPropelConnection());
        $other = $this->factory->productSaleElement($product, ['ref' => 'BOOTS-01-43', 'quantity' => 50]);
        $this->factory->productPrice($other, $this->currency);

        $line = $this->resolveOne('BOOTS-01');

        self::assertSame(LineStatus::Resolved, $line->status);
        self::assertSame((int) $default->getId(), $line->productSaleElementsId);
    }

    public function testSaleElementsOfOneProductSharingItsReferenceAreAmbiguousWithTheDefaultPreselected(): void
    {
        [$product, $default, $second] = $this->productWithTwoSaleElementsSharingItsReference();
        $size = $this->factory->attribute(['title' => 'Size']);
        $this->factory->attributeCombination($default, $this->factory->attributeAv($size, ['title' => 'S']));
        $this->factory->attributeCombination($second, $this->factory->attributeAv($size, ['title' => 'M']));

        $line = $this->resolveOne((string) $product->getRef());

        self::assertSame(LineStatus::Ambiguous, $line->status);
        self::assertNull($line->productSaleElementsId);
        self::assertSame(
            [[(int) $default->getId(), true], [(int) $second->getId(), false]],
            array_map(static fn (Candidate $candidate): array => [$candidate->productSaleElementsId, $candidate->preselected], $line->candidates),
        );
        self::assertSame([['attribute' => 'Size', 'value' => 'M']], $line->candidates[1]->attributes);
    }

    public function testThePreselectionFollowsTheDefaultFlagRatherThanThePosition(): void
    {
        [$product, $first, $second] = $this->productWithTwoSaleElementsSharingItsReference();
        $first->setIsDefault(false)->save($this->getPropelConnection());
        $second->setIsDefault(true)->save($this->getPropelConnection());

        $line = $this->resolveOne((string) $product->getRef());

        self::assertSame(
            [(int) $second->getId()],
            array_values(array_map(
                static fn (Candidate $candidate): int => $candidate->productSaleElementsId,
                array_filter($line->candidates, static fn (Candidate $candidate): bool => $candidate->preselected),
            )),
        );
    }

    public function testTheSaleElementTheBuyerChoseResolvesTheAmbiguity(): void
    {
        [$product, , $second] = $this->productWithTwoSaleElementsSharingItsReference();

        $line = $this->resolveOne((string) $product->getRef(), 2, (int) $second->getId());

        self::assertSame(LineStatus::Resolved, $line->status);
        self::assertSame((int) $second->getId(), $line->productSaleElementsId);
    }

    public function testASaleElementThatDoesNotCarryTheReferenceIsRefused(): void
    {
        [$product] = $this->productWithTwoSaleElementsSharingItsReference();
        $elsewhere = $this->defaultSaleElementsOf($this->product());

        self::assertSame(LineStatus::Unknown, $this->resolveOne((string) $product->getRef(), 1, (int) $elsewhere->getId())->status);
    }

    public function testAReferenceSharedAcrossProductsIsAmbiguousWithoutPreselection(): void
    {
        $first = $this->defaultSaleElementsOf($this->product());
        $second = $this->defaultSaleElementsOf($this->product());
        $second->setRef((string) $first->getRef())->save($this->getPropelConnection());

        $line = $this->resolveOne((string) $first->getRef());

        self::assertSame(LineStatus::Ambiguous, $line->status);
        self::assertCount(2, $line->candidates);
        self::assertSame([false, false], array_map(static fn (Candidate $candidate): bool => $candidate->preselected, $line->candidates));
    }

    public function testAnUnknownReferenceIsReportedAsSuch(): void
    {
        self::assertSame(LineStatus::Unknown, $this->resolveOne('NOTHING-CARRIES-THIS')->status);
    }

    public function testAHiddenProductOrSaleElementAnswersAsUnknown(): void
    {
        $hiddenProduct = $this->product();
        $hiddenProduct->setVisible(0)->save($this->getPropelConnection());
        $hiddenSaleElements = $this->defaultSaleElementsOf($this->product());
        $hiddenSaleElements->setVisible(false)->save($this->getPropelConnection());

        self::assertSame(LineStatus::Unknown, $this->resolveOne((string) $hiddenProduct->getRef())->status);
        self::assertSame(LineStatus::Unknown, $this->resolveOne((string) $hiddenSaleElements->getRef())->status);
    }

    public function testStockDecidesBetweenUnavailableAndRefusedQuantity(): void
    {
        $empty = $this->defaultSaleElementsOf($this->product(['baseQuantity' => 0]));
        $short = $this->defaultSaleElementsOf($this->product(['baseQuantity' => 4]));

        self::assertSame(LineStatus::Unavailable, $this->resolveOne((string) $empty->getRef())->status);

        $line = $this->resolveOne((string) $short->getRef(), 5);
        self::assertSame(LineStatus::QuantityRefused, $line->status);
        self::assertSame(4.0, $line->availableQuantity);
    }

    public function testTheCustomerDiscountLowersTheUnitPrice(): void
    {
        $saleElements = $this->defaultSaleElementsOf($this->product(['basePrice' => 20.0]));
        $this->customer->setDiscount('10')->save($this->getPropelConnection());

        self::assertEqualsWithDelta(18.0, $this->resolveOne((string) $saleElements->getRef())->untaxedUnitPrice, 0.0001);
    }

    public function testTheLinesComeBackInTheOrderTheyWereGiven(): void
    {
        $first = $this->defaultSaleElementsOf($this->product());
        $second = $this->defaultSaleElementsOf($this->product());

        $table = $this->resolver->resolve($this->customer, new ReferenceQuantityLines([
            new ReferenceQuantity((string) $second->getRef(), 1),
            new ReferenceQuantity('UNKNOWN-REF', 1),
            new ReferenceQuantity((string) $first->getRef(), 1),
        ]), $this->currency);

        self::assertSame(
            [(string) $second->getRef(), 'UNKNOWN-REF', (string) $first->getRef()],
            array_map(static fn (QuickOrderLine $line): string => $line->reference, $table->lines),
        );
        self::assertSame(2, $table->summary()['resolved']);
        self::assertSame(1, $table->summary()['unknown']);
    }

    public function testTheNumberOfStatementsDoesNotGrowWithTheNumberOfLines(): void
    {
        $references = [];

        for ($n = 0; $n < 40; ++$n) {
            $references[] = (string) $this->defaultSaleElementsOf($this->product())->getRef();
        }

        // The first resolution also loads what the request keeps for good (the
        // language, the currency, the delivery country): it is left out of the count.
        $this->countStatementsResolving(\array_slice($references, 0, 1));
        $few = $this->countStatementsResolving(\array_slice($references, 0, 4));
        $many = $this->countStatementsResolving($references);

        self::assertSame($few, $many, 'Resolving ten times more lines ran more statements.');
    }

    /**
     * @param list<string> $references
     */
    private function countStatementsResolving(array $references): int
    {
        $lines = new ReferenceQuantityLines(array_map(static fn (string $reference): ReferenceQuantity => new ReferenceQuantity($reference, 1), $references));

        return \count($this->recordSqlQueries(fn () => $this->resolver->resolve($this->customer, $lines, $this->currency)));
    }

    private function resolveOne(string $reference, int $quantity = 1, ?int $productSaleElementsId = null): QuickOrderLine
    {
        $table = $this->resolver->resolve(
            $this->customer,
            new ReferenceQuantityLines([new ReferenceQuantity($reference, $quantity, $productSaleElementsId)]),
            $this->currency,
        );

        return $table->lines[0];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function product(array $overrides = []): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->currency,
            $overrides + ['baseQuantity' => 50],
        );
    }

    private function defaultSaleElementsOf(Product $product): ProductSaleElements
    {
        return $product->getProductSaleElementss()->getFirst()
            ?? throw new \LogicException('The product has no sale element.');
    }

    /**
     * @return array{Product, ProductSaleElements, ProductSaleElements}
     */
    private function productWithTwoSaleElementsSharingItsReference(): array
    {
        $product = $this->product();
        $default = $this->defaultSaleElementsOf($product);
        $default->setIsDefault(true)->save($this->getPropelConnection());
        $second = $this->factory->productSaleElement($product, ['ref' => $product->getRef(), 'quantity' => 50]);
        $this->factory->productPrice($second, $this->currency);

        return [$product, $default, $second];
    }
}
