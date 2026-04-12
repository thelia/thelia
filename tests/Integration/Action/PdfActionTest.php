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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Test\ActionIntegrationTestCase;

final class PdfActionTest extends ActionIntegrationTestCase
{
    public function testGeneratePdfProducesValidOutput(): void
    {
        $event = new PdfEvent('<html><body><h1>Invoice</h1><p>Total: 42.00 EUR</p></body></html>');

        $this->dispatch($event, TheliaEvents::GENERATE_PDF);

        self::assertTrue($event->hasPdf());

        $pdf = $event->getPdf();
        self::assertIsString($pdf);
        self::assertNotEmpty($pdf);
        // PDF files start with the %PDF magic bytes.
        self::assertStringStartsWith('%PDF', $pdf);
    }

    public function testGeneratePdfRespectsOrientationAndFormat(): void
    {
        $event = new PdfEvent(
            '<html><body><p>Landscape A3</p></body></html>',
            'L',
            'A3',
        );

        $this->dispatch($event, TheliaEvents::GENERATE_PDF);

        self::assertTrue($event->hasPdf());
        self::assertStringStartsWith('%PDF', $event->getPdf());
    }
}
