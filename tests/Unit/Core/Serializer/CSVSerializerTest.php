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

namespace Thelia\Tests\Unit\Core\Serializer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Core\Serializer\Serializer\CSVSerializer;

/**
 * Every export written as CSV goes through this serializer, and a CSV file is opened in
 * a spreadsheet: a cell the spreadsheet would read as a formula is written as text.
 */
final class CSVSerializerTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function formulas(): iterable
    {
        yield 'equals' => ['=1+2'];
        yield 'plus' => ['+1+cmd'];
        yield 'minus' => ['-2+3'];
        yield 'at' => ['@SUM(1+1)'];
        yield 'tab' => ["\t=1+2"];
        yield 'carriage return' => ["\r=1+2"];
    }

    #[DataProvider('formulas')]
    public function testACellASpreadsheetWouldRunIsWrittenAsText(string $formula): void
    {
        self::assertSame(["'".$formula], $this->readBack((new CSVSerializer())->serialize(['name' => $formula])));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function plainNumbers(): iterable
    {
        yield 'negative amount' => ['-5.00'];
        yield 'negative integer' => ['-5'];
        yield 'international phone number' => ['+33612345678'];
        yield 'decimal comma' => ['-5,50'];
    }

    #[DataProvider('plainNumbers')]
    public function testAPlainNumberKeepsItsSign(string $number): void
    {
        self::assertSame([$number], $this->readBack((new CSVSerializer())->serialize(['amount' => $number])));
    }

    public function testValuesThatAreNotTextAreWrittenAsBefore(): void
    {
        self::assertSame("-5,-1.5,,1\n", (new CSVSerializer())->serialize(['a' => -5, 'b' => -1.5, 'c' => null, 'd' => true]));
    }

    public function testAValueAlreadyWrittenAsTextIsLeftAlone(): void
    {
        self::assertSame("'=1+2\n", (new CSVSerializer())->serialize(['name' => "'=1+2"]));
    }

    /**
     * A spreadsheet set to the other list separator, as French ones are, splits a bare
     * `Rue;=1+2` into two cells and runs the second one.
     */
    public function testACellHoldingAListSeparatorIsEnclosed(): void
    {
        self::assertSame("\"Rue;=1+2\",Paris\n", (new CSVSerializer())->serialize(['address' => 'Rue;=1+2', 'city' => 'Paris']));
    }

    public function testACellHoldingTheOtherListSeparatorIsEnclosedWhateverTheDelimiter(): void
    {
        self::assertSame("\"Rue,=1+2\";Paris\n", (new CSVSerializer())->setDelimiter(';')->serialize(['address' => 'Rue,=1+2', 'city' => 'Paris']));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function rowsWithoutFormulaNorListSeparator(): iterable
    {
        yield 'bare' => [['ref' => 'PROD001', 'title' => 'Chair']];
        yield 'space' => [['title' => 'Blue chair']];
        yield 'enclosure' => [['title' => 'The "best" chair']];
        yield 'line break' => [['title' => "Blue\nchair"]];
        yield 'tab' => [['title' => "Blue\tchair"]];
        yield 'backslash' => [['title' => 'C:\\path\\"x"']];
        yield 'scalars' => [['a' => 12, 'b' => 1.5, 'c' => null, 'd' => false, 'e' => '']];
        yield 'utf-8' => [['title' => 'Chaise éléphant']];
    }

    /**
     * @param array<string, mixed> $row
     */
    #[DataProvider('rowsWithoutFormulaNorListSeparator')]
    public function testAnyOtherRowIsWrittenAsFputcsvWritesIt(array $row): void
    {
        $handle = fopen('php://memory', 'w+');
        fputcsv($handle, $row, ',', '"', '');
        rewind($handle);
        $expected = stream_get_contents($handle);
        fclose($handle);

        self::assertSame($expected, (new CSVSerializer())->serialize($row));
    }

    /**
     * @return list<string|null>
     */
    private function readBack(string $line): array
    {
        return str_getcsv(rtrim($line, "\n"), ',', '"', '');
    }
}
