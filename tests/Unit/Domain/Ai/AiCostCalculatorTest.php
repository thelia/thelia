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

namespace Thelia\Tests\Unit\Domain\Ai;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\AiTokenUsage;
use Thelia\Domain\Ai\Catalog\AiCostCalculator;
use Thelia\Domain\Ai\Catalog\AiModelSpecification;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Internal\AiDecimal;

final class AiCostCalculatorTest extends TestCase
{
    private AiCostCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new AiCostCalculator();
    }

    public function testItPricesTheTokensWithTheCatalogueAsTheContractExampleDoes(): void
    {
        // API.md, example (4): 1530 + 212 tokens of ministral at 0.04 USD per million, and the Opus calls.
        self::assertSame('0.00006968', $this->calculator->cost($this->model('0.04', '0.04'), new AiTokenUsage(1530, 212)));
        self::assertSame('0.05421000', $this->calculator->cost($this->model('5.00', '25.00'), new AiTokenUsage(4812, 1206)));
        self::assertSame('0.12770000', $this->calculator->cost($this->model('5.00', '25.00'), new AiTokenUsage(6140, 3880)));
    }

    public function testCachedTokensHaveTheirOwnPriceOrFallBackToTheInputPrice(): void
    {
        $priced = $this->model('3.00', '15.00', '0.30', '3.75');
        $unpriced = $this->model('3.00', '15.00');

        self::assertSame('0.00300000', $this->calculator->cost($priced, new AiTokenUsage(0, 0, 10000)));
        self::assertSame('0.03750000', $this->calculator->cost($priced, new AiTokenUsage(0, 0, 0, 10000)));
        self::assertSame('0.03000000', $this->calculator->cost($unpriced, new AiTokenUsage(0, 0, 10000)));
        self::assertSame('0.03000000', $this->calculator->cost($unpriced, new AiTokenUsage(0, 0, 0, 10000)));
    }

    public function testACallIsRoundedHalfUpToTheEightDecimalsOfTheLog(): void
    {
        // 1 token at 0.04 USD per million = 0.00000004 exactly; 1 token at 0.05 = 0.00000005; at 0.044 = 0.000000044 -> 0.00000004.
        self::assertSame('0.00000004', $this->calculator->cost($this->model('0.04', '0'), new AiTokenUsage(1, 0)));
        self::assertSame('0.00000004', $this->calculator->cost($this->model('0.044', '0'), new AiTokenUsage(1, 0)));
        self::assertSame('0.00000005', $this->calculator->cost($this->model('0.045', '0'), new AiTokenUsage(1, 0)));
        self::assertSame('0.00000000', $this->calculator->cost($this->model('0.00', '0.00'), new AiTokenUsage(100000, 100000)));
    }

    public function testTheCostTheProviderAnnouncedWinsOverTheCatalogue(): void
    {
        $model = $this->model('5.00', '25.00');

        self::assertSame('0.00012340', $this->calculator->cost($model, new AiTokenUsage(1000, 1000), '0.00012340'));
        self::assertSame('0.00000012', $this->calculator->cost($model, new AiTokenUsage(1000, 1000), '0.000000123'));
    }

    public function testAnAnnouncedCostThatIsNotADecimalFallsBackToTheCatalogue(): void
    {
        $model = $this->model('5.00', '25.00');

        self::assertSame('0.03000000', $this->calculator->cost($model, new AiTokenUsage(1000, 1000), '1.2E-7'));
        self::assertSame('0.03000000', $this->calculator->cost($model, new AiTokenUsage(1000, 1000), '-1'));
    }

    public function testTheReservationCountsThreeCharactersPerInputTokenAndTheWholeOutputLimit(): void
    {
        // 3000 characters = 1000 tokens at 5 USD per million, plus 16000 output tokens at 25.
        self::assertSame('0.40500000', $this->calculator->reservation($this->model('5.00', '25.00'), 3000, 16000));
        // A partial token counts as a whole one.
        self::assertSame('0.00001000', $this->calculator->reservation($this->model('5.00', '0.00'), 4, 0));
        self::assertSame(2, AiCostCalculator::tokensOf(4));
        self::assertSame(0, AiCostCalculator::tokensOf(0));
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function decimals(): iterable
    {
        yield 'integer' => ['5', 6, 5_000_000];
        yield 'price' => ['0.040000', 6, 40_000];
        yield 'long fraction rounds up' => ['0.0000000050', 8, 1];
        yield 'long fraction rounds down' => ['0.0000000049', 8, 0];
        yield 'database decimal' => ['12.34500000', 8, 1_234_500_000];
    }

    #[DataProvider('decimals')]
    public function testDecimalsAreReadAtAScale(string $value, int $scale, int $expected): void
    {
        self::assertSame($expected, AiDecimal::parse($value, $scale));
    }

    public function testDecimalsAreWrittenBackFromTheirUnits(): void
    {
        self::assertSame('0.00006968', AiDecimal::format(6968));
        self::assertSame('12.00000001', AiDecimal::format(1_200_000_001));
        self::assertSame('0.00000000', AiDecimal::format(0));
    }

    #[DataProvider('notDecimals')]
    public function testWhatIsNotADecimalIsRefused(string $value): void
    {
        $this->expectException(InvalidAiArgumentException::class);

        AiDecimal::parse($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notDecimals(): iterable
    {
        yield 'exponent' => ['1.2E-7'];
        yield 'negative' => ['-1'];
        yield 'comma' => ['1,5'];
        yield 'empty' => [''];
        yield 'too large for the units' => ['12345678901'];
    }

    private function model(string $input, string $output, ?string $cacheRead = null, ?string $cacheWrite = null): AiModelSpecification
    {
        return new AiModelSpecification('mistral', 'probe', null, $input, $output, $cacheRead, $cacheWrite, 'USD', null, null, true, true, false, true, AiModelSpecification::SOURCE_CATALOG);
    }
}
