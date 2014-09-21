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
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Pdf extends BaseAction implements EventSubscriberInterface
{
    public function generatePdf(PdfEvent $event)
    {
        $html2pdf = new \HTML2PDF($event->getOrientation(), $event->getFormat(), $event->getLang(), $event->getUnicode(), $event->getEncoding(), $event->getMarges());

        $html2pdf->pdf->SetDisplayMode('real');

        $html2pdf->writeHTML($event->getContent());
        $event->setPdf($html2pdf->output(null, 'S'));
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::GENERATE_PDF => array("generatePdf", 128)
        );
    }
}
