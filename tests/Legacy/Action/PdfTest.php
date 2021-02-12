<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Action;

use PHPUnit\Framework\TestCase;
use Thelia\Action\Pdf;
use Thelia\Core\Event\PdfEvent;

/**
 * Class PdfTest.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class PdfTest extends TestCase
{
    public function testGeneratePdf(): void
    {
        $event = new PdfEvent('test content');

        $action = new Pdf();
        $action->generatePdf($event);

        $generatedPdf = $event->getPdf();

        $this->assertNotNull($generatedPdf);
    }
}
