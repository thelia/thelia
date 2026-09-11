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

namespace Thelia\Domain\Promotion\Coupon\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Promotion\Coupon\OfferedLine\OfferedLineProviderInterface;
use Thelia\Domain\Promotion\Coupon\OfferedLine\OfferedLineRequest;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Log\Tlog;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CouponQuery;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElements;

/**
 * Brings the offered lines of a cart in line with what the kept promotions ask
 * for: missing lines are added, quantities adjusted, lines no promotion asks for
 * any more removed.
 *
 * Every write is a direct Propel write, never a cart event: dispatching
 * CART_ADDITEM from an effect is the infinite-loop trap FreeProduct documents
 * (exec() -> CART_ADDITEM -> updateOrderDiscount() -> exec()). The re-entrance
 * guard protects against any listener reacting to the low-level writes anyway.
 *
 * The promotions whose gift could not be granted for lack of stock are written
 * to the session on every reconciliation (full replacement), so a page rendered
 * by a LATER request — the redirect after a cart mutation — can still announce
 * them: within one request the in-memory state answers, on any other the
 * session does. Their labels are resolved in the locale the session browses in.
 */
class OfferedCartLineService implements ResetInterface
{
    public const UNAVAILABLE_PROMOTIONS_SESSION_KEY = 'thelia.cart.unavailable_promotions';

    private bool $reconciling = false;

    /** @var bool whether a reconciliation ran during this request */
    private bool $reconciledThisRequest = false;

    /** @var array<int, string> promotion label per coupon id, when stock prevented the offered line */
    private array $unavailablePromotions = [];

    public function __construct(
        private readonly FacadeInterface $facade,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param CouponInterface[] $keptCoupons the coupons the evaluation retained
     *
     * @return bool whether the cart lines changed
     */
    public function reconcile(array $keptCoupons, Cart $cart): bool
    {
        if ($this->reconciling) {
            // Re-entered through a listener reacting to our own writes: the outer run owns the work.
            return false;
        }

        $this->reconciling = true;

        try {
            $changed = $this->reconcileOfferedLines($keptCoupons, $cart);
        } finally {
            $this->reconciling = false;
        }

        $this->reconciledThisRequest = true;
        $this->getSession()?->set(self::UNAVAILABLE_PROMOTIONS_SESSION_KEY, array_values($this->unavailablePromotions));

        return $changed;
    }

    /**
     * @return string[] labels of the promotions whose offered line could not be added for lack of stock
     */
    public function getUnavailablePromotions(): array
    {
        if ($this->reconciledThisRequest) {
            return array_values($this->unavailablePromotions);
        }

        // No reconciliation ran during this request: the state of the last one lives
        // in the session. Without a session (CLI), the in-memory state is all there is.
        $session = $this->getSession();

        if (!$session instanceof Session) {
            return array_values($this->unavailablePromotions);
        }

        $stored = $session->get(self::UNAVAILABLE_PROMOTIONS_SESSION_KEY, []);

        if (!\is_array($stored)) {
            return [];
        }

        return array_values(array_filter($stored, '\is_string'));
    }

    public function reset(): void
    {
        $this->unavailablePromotions = [];
        $this->reconciledThisRequest = false;
    }

    /**
     * @param CouponInterface[] $keptCoupons
     */
    private function reconcileOfferedLines(array $keptCoupons, Cart $cart): bool
    {
        $this->unavailablePromotions = [];

        $requests = [];
        $fallbackLabels = [];

        foreach ($keptCoupons as $coupon) {
            if (!$coupon instanceof OfferedLineProviderInterface || !$coupon instanceof CouponInterface) {
                continue;
            }

            foreach ($coupon->getOfferedLineRequests($this->facade) as $request) {
                $key = $request->couponId.':'.$request->productId;

                $requests[$key] = isset($requests[$key])
                    ? new OfferedLineRequest($request->productId, $requests[$key]->quantity + $request->quantity, $request->couponId)
                    : $request;

                $fallbackLabels[$request->couponId] = '' !== $coupon->getTitle() ? $coupon->getTitle() : $coupon->getCode();
            }
        }

        $changed = false;

        // A key can hold several rows when past writes went wrong (a crash between
        // delete and insert, a legacy duplication): the first row is the one
        // reconciled, the supernumerary ones are plain corruption to remove.
        $offeredLines = [];

        /** @var CartItem $line */
        foreach (CartItemQuery::create()->filterByCartId($cart->getId())->filterByIsOffered(1)->orderById()->find() as $line) {
            $offeredLines[$line->getOfferedByCouponId().':'.$line->getProductId()][] = $line;
        }

        foreach ($requests as $key => $request) {
            $label = $this->promotionLabel($request->couponId, $fallbackLabels[$request->couponId]);
            $lines = $offeredLines[$key] ?? [];
            unset($offeredLines[$key]);

            $line = array_shift($lines);

            foreach ($lines as $supernumerary) {
                $supernumerary->delete();
                $changed = true;
            }

            $changed = (null !== $line
                ? $this->updateOfferedLine($line, $request, $label, $cart)
                : $this->addOfferedLine($cart, $request, $label)) || $changed;
        }

        // Lines no kept promotion asks for any more.
        foreach ($offeredLines as $orphans) {
            foreach ($orphans as $orphan) {
                $orphan->delete();
                $changed = true;
            }
        }

        if ($changed) {
            // Loaded collections still hold the previous lines: force a re-read.
            $cart->clearCartItems();
        }

        return $changed;
    }

    private function updateOfferedLine(CartItem $line, OfferedLineRequest $request, string $label, Cart $cart): bool
    {
        $productSaleElements = $line->getProductSaleElements();
        $grantable = $this->grantableQuantity($line->getProduct(), $productSaleElements, $request->quantity);

        if (0 === $grantable) {
            $this->unavailablePromotions[$request->couponId] = $label;
            $line->delete();

            return true;
        }

        $modified = false;

        if ((int) $line->getQuantity() !== $grantable) {
            $line->setQuantity($grantable);
            $modified = true;
        }

        $priceEndOfLife = $line->getPriceEndOfLife();

        if ($priceEndOfLife instanceof \DateTimeInterface && $priceEndOfLife < new \DateTime()) {
            // The pinned price expired: pin it again on the current catalog price,
            // the way any cart line is repriced when its price end of life passes.
            $this->pinPrices($line, $productSaleElements, $cart);
            $modified = true;
        }

        if ($modified) {
            $line->save();
        }

        return $modified;
    }

    private function addOfferedLine(Cart $cart, OfferedLineRequest $request, string $label): bool
    {
        $product = ProductQuery::create()->findPk($request->productId);

        if (null === $product) {
            Tlog::getInstance()->warning(
                \sprintf('Offered product %d no longer exists, promotion "%s" adds nothing', $request->productId, $label),
            );

            return false;
        }

        try {
            $productSaleElements = $product->getDefaultSaleElements();
        } catch (\Exception $exception) {
            Tlog::getInstance()->warning(
                \sprintf('Offered product %d has no default sale element: %s', $request->productId, $exception->getMessage()),
            );

            return false;
        }

        $grantable = $this->grantableQuantity($product, $productSaleElements, $request->quantity);

        if (0 === $grantable) {
            $this->unavailablePromotions[$request->couponId] = $label;

            return false;
        }

        $line = new CartItem();
        $line
            ->setCart($cart)
            ->setProductId($request->productId)
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setQuantity($grantable)
            ->setIsOffered(1)
            ->setOfferedByCouponId($request->couponId);

        $this->pinPrices($line, $productSaleElements, $cart);

        $line->save();

        return true;
    }

    /**
     * Pins the line prices the way Action\Cart::doAddItem() does: the catalog
     * prices for the cart currency, the customer discount applied, and a fresh
     * price end of life.
     */
    private function pinPrices(CartItem $line, ProductSaleElements $productSaleElements, Cart $cart): void
    {
        $prices = $productSaleElements->getPricesByCurrency(
            $cart->getCurrency() ?? Currency::getDefaultCurrency(),
            $this->customerDiscountOf($cart),
        );

        $line
            ->setPrice((string) $prices->getPrice())
            ->setPromoPrice((string) $prices->getPromoPrice())
            ->setPromo((int) $productSaleElements->getPromo())
            ->setPriceEndOfLife(time() + (int) ConfigQuery::read('cart.priceEOF', 60 * 60 * 24 * 30));
    }

    private function customerDiscountOf(Cart $cart): float
    {
        $customer = $cart->getCustomer();

        if (null === $customer) {
            return 0.0;
        }

        // getDiscount() maps a DECIMAL column and returns a string.
        return (float) $customer->getDiscount() > 0 ? (float) $customer->getDiscount() : 0.0;
    }

    /**
     * How many units of the request the stock allows: all of them when the stock
     * is not checked (config, virtual product), what is left when it is. Zero
     * means the promotion cannot be granted at all.
     */
    private function grantableQuantity(Product $product, ProductSaleElements $productSaleElements, int $requestedQuantity): int
    {
        if (!ConfigQuery::checkAvailableStock()) {
            return $requestedQuantity;
        }

        if (1 === (int) $product->getVirtual()) {
            return $requestedQuantity;
        }

        return max(0, min($requestedQuantity, (int) $productSaleElements->getQuantity()));
    }

    /**
     * The public title of the promotion in the language the session browses in,
     * the same way the cart page resolves the discount labels: the coupon
     * instance carries the title of the locale it was built with, which is not
     * necessarily the one the visitor reads.
     */
    private function promotionLabel(int $couponId, string $fallback): string
    {
        $locale = $this->getSession()?->getLang()?->getLocale();

        if (null === $locale) {
            return $fallback;
        }

        $model = CouponQuery::create()->findPk($couponId);

        if (null === $model) {
            return $fallback;
        }

        $title = (string) $model->setLocale($locale)->getTitle();

        return '' !== $title ? $title : $fallback;
    }

    private function getSession(): ?Session
    {
        $request = $this->requestStack->getMainRequest();

        if (null === $request || !$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();

        return $session instanceof Session ? $session : null;
    }
}
