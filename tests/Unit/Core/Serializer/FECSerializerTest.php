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

use PHPUnit\Framework\TestCase;
use Thelia\Core\Serializer\Serializer\FECSerializer;

/**
 * The French accounting entries file: tab separated, its field names on the first line,
 * dates as YYYYMMDD and amounts with a decimal comma, nothing a spreadsheet would want.
 */
final class FECSerializerTest extends TestCase
{
    public function testValuesAreWrittenTheFecWay(): void
    {
        $serializer = new FECSerializer();
        $file = new \SplFileObject('php://memory', 'w+b');
        $serializer->prepareFile($file);

        $fields = static fn (array $values): array => array_values(array_merge(array_fill_keys(FECSerializer::FIELDS, ''), $values));
        $written = $serializer->serialize([
            'JournalCode' => 'VE',
            'EcritureDate' => '2026-02-10',
            'EcritureLib' => "Facture 2026-000012\tACME\r\nSARL",
            'Debit' => '132.00',
            'Credit' => '0.00',
        ]);

        self::assertSame(implode("\t", $fields(['JournalCode' => 'VE', 'EcritureDate' => '20260210', 'EcritureLib' => 'Facture 2026-000012 ACME  SARL', 'Debit' => '132,00', 'Credit' => '0,00']))."\r\n", $written);
        self::assertSame(implode("\t", $fields(['JournalCode' => 'VE', 'EcritureLib' => '-=label', 'Debit' => '-5,00', 'Credit' => '0,00']))."\r\n", $serializer->serialize([
            'JournalCode' => 'VE',
            'EcritureLib' => '-=label',
            'Debit' => '-5.00',
            'Credit' => '0.00',
        ]), 'No formula guard: an accounting program reads the label as it is.');
    }

    public function testWhatItWritesReadsBack(): void
    {
        $serializer = new FECSerializer();
        $file = new \SplFileObject('php://memory', 'w+b');
        $serializer->prepareFile($file);
        $file->fwrite($serializer->serialize(['JournalCode' => 'VE', 'Debit' => '1.50']));
        $file->fwrite($serializer->serialize(['JournalCode' => 'VE', 'Debit' => '2.00']));
        $file->rewind();
        $rows = $serializer->unserialize($file);

        self::assertCount(2, $rows);
        self::assertSame(['VE', '1,50'], [$rows[0]['JournalCode'], $rows[0]['Debit']]);
        self::assertSame(['VE', '2,00'], [$rows[1]['JournalCode'], $rows[1]['Debit']]);
    }

    public function testTheFieldNamesAreWrittenEvenWithoutAnyEntry(): void
    {
        $serializer = new FECSerializer();
        $file = new \SplFileObject('php://memory', 'w+b');

        $serializer->prepareFile($file);
        $file->rewind();

        self::assertSame(implode("\t", FECSerializer::FIELDS)."\r\n", (string) $file->fgets());
    }

    public function testTheFieldsAreWrittenInTheOrderOfTheFormatWhateverTheRow(): void
    {
        $serializer = new FECSerializer();
        $serializer->prepareFile(new \SplFileObject('php://memory', 'w+b'));

        $line = $serializer->serialize(['Credit' => '1.00', 'JournalCode' => 'VE']);

        self::assertSame("VE\t\t\t\t\t\t\t\t\t\t\t\t1,00\t\t\t\t\t\r\n", $line);
    }

    public function testItIsAPlainTextFile(): void
    {
        $serializer = new FECSerializer();

        self::assertSame('thelia.fec', $serializer->getId());
        self::assertSame('txt', $serializer->getExtension());
        self::assertSame('text/plain', $serializer->getMimeType());
    }
}
