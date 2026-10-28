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

namespace Thelia\Domain\Ai\Tool\Builtin;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Ai\Tool\AiApprovedChange;
use Thelia\Domain\Ai\Tool\AiChangeProposal;
use Thelia\Domain\Ai\Tool\AiToolContext;
use Thelia\Domain\Ai\Tool\AiWriteToolInterface;
use Thelia\Domain\Ai\Tool\AsAiTool;
use Thelia\Domain\Ai\Tool\Exception\AiStaleChangeException;
use Thelia\Domain\Ai\Tool\Exception\AiToolFailedException;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Proposes a new pre-tax price for one variant, in the default currency. Nothing changes until an
 * administrator approves it; the approval applies it through the same event as the back office.
 */
#[AsAiTool(
    name: 'thelia_update_product_price',
    description: 'Proposes a new pre-tax price for one variant (pse_id from thelia_search_products), in the default currency. Nothing changes until an administrator approves it.',
    resource: AdminResources::PRODUCT,
    access: AccessManager::UPDATE,
)]
final readonly class UpdateProductPriceTool implements AiWriteToolInterface
{
    private const string TARGET_PREFIX = 'product_sale_elements:';

    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['pse_id', 'price'],
            'properties' => [
                'pse_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The variant to reprice'],
                'price' => ['type' => 'string', 'pattern' => '^[0-9]{1,10}(\\.[0-9]{1,6})?$', 'description' => 'The new pre-tax price as a decimal string, like "14.90"'],
            ],
        ];
    }

    public function propose(array $arguments, AiToolContext $context): AiChangeProposal
    {
        $pseId = (int) $arguments['pse_id'];
        $pse = ProductSaleElementsQuery::create()->findPk($pseId) ?? throw new AiToolFailedException('This variant does not exist.');
        $currency = $this->defaultCurrency();
        $current = $this->priceOf($pseId, $currency) ?? throw new AiToolFailedException('This variant has no price in the default currency.');
        $after = self::normalize((string) $arguments['price']);

        return new AiChangeProposal(
            self::TARGET_PREFIX.$pseId,
            \sprintf('Price of %s: %s → %s %s excl. tax', $pse->getRef(), $current->getPrice(), $after, $currency->getCode()),
            ['price' => self::normalize($current->getPrice())],
            ['price' => $after],
        );
    }

    public function apply(AiApprovedChange $change): void
    {
        $pseId = (int) substr($change->target, \strlen(self::TARGET_PREFIX));
        $pse = ProductSaleElementsQuery::create()->findPk($pseId) ?? throw new AiStaleChangeException('The variant no longer exists.');
        $currency = $this->defaultCurrency();
        $current = $this->priceOf($pseId, $currency) ?? throw new AiStaleChangeException('The variant no longer has a price in the default currency.');
        $expected = (string) ($change->before['price'] ?? '');

        if (self::normalize($current->getPrice()) !== $expected) {
            throw new AiStaleChangeException('The price changed since the proposal.');
        }

        $newPrice = (string) ($change->after['price'] ?? '');

        $this->dispatcher->dispatch($this->event($pse, $current, $currency, (float) $newPrice), TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);

        $stored = $this->priceOf($pseId, $currency);

        if (null === $stored || self::normalize($stored->getPrice()) !== $newPrice) {
            throw new AiToolFailedException('The price could not be stored exactly: nothing was changed.');
        }
    }

    /**
     * The event of the back office carries every field of the variant, and the update writes them all: it starts
     * from the current values and changes the price only.
     */
    private function event(ProductSaleElements $pse, ProductPrice $price, Currency $currency, float $newPrice): ProductSaleElementUpdateEvent
    {
        $product = $pse->getProduct();

        return (new ProductSaleElementUpdateEvent($product, $pse->getId()))
            ->setReference((string) $pse->getRef())
            ->setPrice($newPrice)
            ->setCurrencyId($currency->getId())
            ->setWeight((float) $pse->getWeight())
            ->setQuantity((float) $pse->getQuantity())
            ->setSalePrice((float) $price->getPromoPrice())
            ->setOnsale($pse->getPromo() ? 1 : 0)
            ->setIsnew($pse->getNewness() ? 1 : 0)
            ->setIsdefault((bool) $pse->getIsDefault())
            ->setEanCode((string) $pse->getEanCode())
            ->setTaxRuleId((int) $product->getTaxRuleId())
            ->setFromDefaultCurrency(0);
    }

    private function defaultCurrency(): Currency
    {
        return CurrencyQuery::create()->filterByByDefault(true)->findOne() ?? throw new AiToolFailedException('The shop has no default currency.');
    }

    private function priceOf(int $pseId, Currency $currency): ?ProductPrice
    {
        return ProductPriceQuery::create()
            ->filterByProductSaleElementsId($pseId)
            ->filterByCurrencyId($currency->getId())
            ->findOne();
    }

    /**
     * A decimal string with six decimals, the scale of the column: "14.9" and "14.90" are the same price.
     */
    private static function normalize(string $price): string
    {
        [$integer, $fraction] = array_pad(explode('.', $price, 2), 2, '');

        $integer = ltrim($integer, '0');

        return ('' === $integer ? '0' : $integer).'.'.str_pad(substr($fraction, 0, 6), 6, '0');
    }
}
