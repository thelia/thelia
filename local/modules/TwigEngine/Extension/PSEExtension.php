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

namespace TwigEngine\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use TwigEngine\Service\DataAccess\ProductSaleElementsAccessService;

class PSEExtension extends AbstractExtension
{
    public function __construct(
        private readonly ProductSaleElementsAccessService $pseAccessService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('psesByProduct', [$this->pseAccessService, 'psesByProduct']),
        ];
    }
}
