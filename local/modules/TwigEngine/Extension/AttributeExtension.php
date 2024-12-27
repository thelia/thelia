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

use Thelia\Service\Model\AttributeService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AttributeExtension extends AbstractExtension
{
    public function __construct(
        private readonly AttributeService $attributeService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getAttributesAndValues', [$this->attributeService, 'getAttributesAndValues']),
        ];
    }
}
