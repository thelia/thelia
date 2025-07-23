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

namespace Thelia\Action;

use Spipu\Html2Pdf\Html2Pdf;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Class Pdf.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Pdf extends BaseAction implements EventSubscriberInterface
{
    public function generatePdf(PdfEvent $event): void
    {
        $html2pdf = new Html2Pdf(
            $event->getOrientation(),
            $event->getFormat(),
            $event->getLang(),
            $event->getUnicode(),
            $event->getEncoding(),
            $event->getMarges(),
        );

        $html2pdf->setDefaultFont($event->getFontName());

        $html2pdf->pdf->SetDisplayMode('real');

        $html2pdf->writeHTML($event->getContent());

        $event->setPdf($html2pdf->output('output.pdf', 'S'));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::GENERATE_PDF => ['generatePdf', 128],
        ];
    }
}
