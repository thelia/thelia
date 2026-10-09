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
    public function testTheFirstRowIsPrecededByTheFieldNamesAndValuesAreWrittenTheFecWay(): void
    {
        $serializer = new FECSerializer();
        $file = new \SplFileObject('php://memory', 'w+b');
        $serializer->prepareFile($file);

        $written = $serializer->serialize([
            'JournalCode' => 'VE',
            'EcritureDate' => '2026-02-10',
            'EcritureLib' => "Facture 2026-000012\tACME\r\nSARL",
            'Debit' => '132.00',
            'Credit' => '0.00',
            'Montantdevise' => '',
            'DateLet' => '',
        ]);

        self::assertSame(
            "JournalCode\tEcritureDate\tEcritureLib\tDebit\tCredit\tMontantdevise\tDateLet\r\n"
            ."VE\t20260210\tFacture 2026-000012 ACME  SARL\t132,00\t0,00\t\t\r\n",
            $written,
        );
        self::assertSame("VE\t20260211\t-=label\t-5,00\t0,00\t\t\r\n", $serializer->serialize([
            'JournalCode' => 'VE',
            'EcritureDate' => '2026-02-11',
            'EcritureLib' => '-=label',
            'Debit' => '-5.00',
            'Credit' => '0.00',
            'Montantdevise' => '',
            'DateLet' => '',
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

        self::assertSame([['JournalCode' => 'VE', 'Debit' => '1,50'], ['JournalCode' => 'VE', 'Debit' => '2,00']], $serializer->unserialize($file));
    }

    public function testItIsAPlainTextFile(): void
    {
        $serializer = new FECSerializer();

        self::assertSame('thelia.fec', $serializer->getId());
        self::assertSame('txt', $serializer->getExtension());
        self::assertSame('text/plain', $serializer->getMimeType());
    }
}
