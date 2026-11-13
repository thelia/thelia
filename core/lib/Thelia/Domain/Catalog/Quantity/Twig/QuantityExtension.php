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

namespace Thelia\Domain\Catalog\Quantity\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Domain\Catalog\Quantity\QuantityFormatter;
use Thelia\Domain\Catalog\Quantity\UnitCode;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * `{{ quantity|format_quantity(decimals, unitCode, locale) }}`: a quantity with the decimals of its combination
 * and the symbol of its unit, in the language given or else the one of the current request ("1,25 kg").
 *
 * It needs no request: the e-mail and PDF templates, rendered from the console, pass the language of their document.
 * A theme may declare a filter of the same name; Twig keeps the last one registered.
 */
final class QuantityExtension extends AbstractExtension
{
    public function __construct(
        private readonly QuantityFormatter $formatter,
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_quantity', $this->formatQuantity(...)),
        ];
    }

    public function formatQuantity(float|int|string|null $quantity, int|string|null $decimals = 0, ?string $unitCode = null, ?string $locale = null): string
    {
        return $this->formatter->format(
            (float) $quantity,
            (int) $decimals,
            UnitCode::tryFrom((string) $unitCode),
            $locale ?? $this->requestStack?->getCurrentRequest()?->getLocale(),
        );
    }
}
