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

use Dompdf\Dompdf;
use Dompdf\Options;
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
        // Page size and margins are driven by the template CSS (@page); the PdfEvent options
        // (orientation, format, font) still steer the defaults so listeners keep control.
        $orientation = strtoupper((string) $event->getOrientation()) === 'L' ? 'landscape' : 'portrait';

        $fontName = $event->getFontName();
        if ('' === $fontName || 'freesans' === $fontName) {
            // dompdf does not ship "freesans"; DejaVu Sans is its bundled Unicode sans-serif
            // (renders € and accented characters), matching the previous html2pdf default.
            $fontName = 'DejaVu Sans';
        }

        $options = new Options();
        $options->set('defaultFont', $fontName);
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultPaperSize', $event->getFormat());
        $options->set('defaultPaperOrientation', $orientation);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper($event->getFormat(), $orientation);
        $dompdf->loadHtml($event->getContent(), $event->getEncoding());
        $dompdf->render();

        $event->setPdf($dompdf->output());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::GENERATE_PDF => ['generatePdf', 128],
        ];
    }
}
