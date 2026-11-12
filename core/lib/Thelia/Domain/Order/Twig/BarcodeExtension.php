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

namespace Thelia\Domain\Order\Twig;

use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Barcodes for templates, the PDF documents first. Pure PHP: no extension, no remote call, so it works
 * in the chroot dompdf runs in, where nothing remote is fetched.
 */
final class BarcodeExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('barcode_code128', $this->code128DataUri(...)),
        ];
    }

    /**
     * The Code 128 barcode of a value as a `data:image/svg+xml;base64,…` URI, to put in the `src` of an `<img>`
     * (`<img src="{{ barcode_code128(order.ref) }}" alt="{{ order.ref }}">`), the form dompdf draws. The SVG has
     * no text under the bars: the template prints the value itself.
     *
     * @param float $barWidth width of the narrowest bar, in SVG units
     * @param int   $height   height of the bars, in SVG units
     *
     * @throws \InvalidArgumentException when the value is empty
     */
    public function code128DataUri(string $value, float $barWidth = 2.0, int $height = 40): string
    {
        if ('' === $value) {
            throw new \InvalidArgumentException('A barcode cannot encode an empty value.');
        }

        $svg = (new BarcodeGeneratorSVG())->getBarcode($value, BarcodeGenerator::TYPE_CODE_128, $barWidth, $height);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
