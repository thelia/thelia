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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Class Pdf
 * @package Thelia\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Pdf extends BaseAction implements EventSubscriberInterface
{
    public function generatePdf(PdfEvent $event)
    {
        $html2pdf = new \HTML2PDF(
            $event->getOrientation(),
            $event->getFormat(),
            $event->getLang(),
            $event->getUnicode(),
            $event->getEncoding(),
            $event->getMarges()
        );

        //*** *** Modified part : (mbruchet)
        // Test if is null or empty then if it's empty or null set those variable to default like in HTML2PDF

        if(is_null($event->getTestTdInOnePage()) || empty($event->getTestTdInOnePage()))
        {
            $html2pdf->setTestTdInOnePage(true);
        }
        else
        {
            $html2pdf->setTestTdInOnePage($event->getTestTdInOnePage());
        }


        if(is_null($event->getTestIsImage()) || empty($event->getTestIsImage()))
        {
            $html2pdf->setTestIsImage(true);
        }
        else
        {
            $html2pdf->setTestIsImage($event->getTestIsImage());
        }

        $html2pdf->pdf->setPage($event->getPage());


        //*** *** End

        $html2pdf->setDefaultFont($event->getFontName());

        $html2pdf->pdf->SetDisplayMode('real');

        $html2pdf->writeHTML($event->getContent());
        $event->setPdf($html2pdf->output(null, 'S'));
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::GENERATE_PDF => array("generatePdf", 128)
        );
    }
}
