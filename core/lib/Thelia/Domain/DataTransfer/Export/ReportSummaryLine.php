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

namespace Thelia\Domain\DataTransfer\Export;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Template\Helper\FormatService;
use Thelia\Model\Currency;

/**
 * One figure of the summary an e-mailed report opens with: the label already translated
 * to the language of the e-mail and the value already formatted.
 */
#[Exclude]
final readonly class ReportSummaryLine
{
    public function __construct(
        public string $label,
        public string $value,
    ) {
    }

    /**
     * An amount of the shop currency, formatted for the language of the e-mail.
     */
    public static function money(string $label, float $amount, string $locale): self
    {
        return new self($label, (new FormatService(new RequestStack()))->money($amount, Currency::getDefaultCurrency()->getId(), $locale));
    }
}
