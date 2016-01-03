<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Tests\Action;

use Thelia\Action\Pdf;
use Thelia\Core\Event\PdfEvent;

/**
 * Class PdfTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class PdfTest extends \PHPUnit_Framework_TestCase
{
    public function testGeneratePdf()
    {
        $event = new PdfEvent("test content");

        $action = new Pdf();
        $action->generatePdf($event);

        $generatedPdf = $event->getPdf();

        $this->assertNotNull($generatedPdf);
    }
}
