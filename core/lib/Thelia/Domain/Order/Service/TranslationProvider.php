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

use Thelia\Model\AttributeAvI18n;
use Thelia\Model\AttributeI18n;
use Thelia\Model\ProductI18n;
use Thelia\Model\TaxRuleI18n;
use Thelia\Tools\I18n;

class TranslationProvider
{
    /** @var array<string, mixed> */
    private array $cache = [];

    public function getProductTranslation(string $locale, int $productId): ProductI18n
    {
        $key = "product:$locale:$productId";

        return $this->cache[$key] ??= I18n::forceI18nRetrieving($locale, 'Product', $productId);
    }

    public function getTaxRuleTranslation(string $locale, int $taxRuleId): TaxRuleI18n
    {
        $key = "taxrule:$locale:$taxRuleId";

        return $this->cache[$key] ??= I18n::forceI18nRetrieving($locale, 'TaxRule', $taxRuleId);
    }

    public function getAttributeTranslation(string $locale, int $attributeId): AttributeI18n
    {
        $key = "attribute:$locale:$attributeId";

        return $this->cache[$key] ??= I18n::forceI18nRetrieving($locale, 'Attribute', $attributeId);
    }

    public function getAttributeAvTranslation(string $locale, int $attributeAvId): AttributeAvI18n
    {
        $key = "attributeav:$locale:$attributeAvId";

        return $this->cache[$key] ??= I18n::forceI18nRetrieving($locale, 'AttributeAv', $attributeAvId);
    }
}
