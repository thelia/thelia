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

namespace Thelia\Tools;

use Symfony\Component\HttpFoundation\Request;

class NumberFormat
{
    public function __construct(protected Request $request)
    {
    }

    public static function getInstance(Request $request): self
    {
        return new self($request);
    }

    /**
     * Get a standard number, with '.' as decimal point and no thousands separator
     * so that this number can be used to perform calculations.
     *
     * @param float  $number   the number
     * @param string $decimals number of decimal figures
     */
    public function formatStandardNumber(float $number, ?string $decimals = null): string
    {
        $lang = $this->request->getSession()->getLang();

        if (null === $decimals) {
            $decimals = $lang->getDecimals();
        }

        return number_format($number, (int) $decimals, '.', '');
    }

    public function format($number, $decimals = null, $decPoint = null, $thousandsSep = null): string
    {
        $lang = $this->request->getSession()->getLang();

        if (null === $decimals) {
            $decimals = $lang->getDecimals();
        }

        if (null === $decPoint) {
            $decPoint = $lang->getDecimalSeparator();
        }

        if (null === $thousandsSep) {
            $thousandsSep = $lang->getThousandsSeparator();
        }

        return number_format((float) $number, (int) $decimals, $decPoint, $thousandsSep);
    }
}
