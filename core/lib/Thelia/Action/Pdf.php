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
