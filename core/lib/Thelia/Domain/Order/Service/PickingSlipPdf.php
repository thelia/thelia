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

namespace Thelia\Domain\Order\Service;

/**
 * A rendered picking slip: the bytes of the PDF and the file name to give it.
 */
final readonly class PickingSlipPdf
{
    public function __construct(
        public string $content,
        public string $fileName,
        public int $orderCount,
    ) {
    }
}
