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

namespace Thelia\Tests\Unit\Domain\DataTransfer;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\DataTransfer\Export\AbstractExport;
use Thelia\Domain\DataTransfer\Export\ArrayAbstractExport;

/**
 * The dates an export reads from the models reach the serializer as text, `Y-m-d H:i:s`:
 * a CSV or a JSON file has no way to write a date object.
 */
final class ExportDateSerializationTest extends TestCase
{
    public function testADateIsWrittenAsText(): void
    {
        self::assertSame(
            ['ref' => 'A1', 'created_at' => '2026-03-10 10:00:00'],
            $this->export()->beforeSerialize(['ref' => 'A1', 'created_at' => new \DateTime('2026-03-10 10:00:00')]),
        );
    }

    public function testAnImmutableDateIsWrittenAsTextToo(): void
    {
        self::assertSame(
            ['created_at' => '2026-03-10 10:00:00'],
            $this->export()->beforeSerialize(['created_at' => new \DateTimeImmutable('2026-03-10 10:00:00')]),
        );
    }

    private function export(): AbstractExport
    {
        return new class extends ArrayAbstractExport {
            protected function getData(): array
            {
                return [];
            }
        };
    }
}
