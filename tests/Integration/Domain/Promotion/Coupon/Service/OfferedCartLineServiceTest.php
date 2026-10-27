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

namespace Thelia\Tests\Integration\Domain\Promotion\Coupon\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Cart\Service\CartLinePricer;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Promotion\Coupon\OfferedLine\OfferedLineProviderInterface;
use Thelia\Domain\Promotion\Coupon\OfferedLine\OfferedLineRequest;
use Thelia\Domain\Promotion\Coupon\Service\OfferedCartLineService;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Coupon;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Tests\Integration\Domain\Promotion\ActionIntegrationPromotionTestCase;

/**
 * The reconciliation on its own, fed with the offers a promotion would ask for:
 * what it writes to the cart, what it reports as a change, and what it tells
 * about the promotions it could not grant.
 */
final class OfferedCartLineServiceTest extends ActionIntegrationPromotionTestCase
{
    public function testAnOfferMissingFromTheCartIsAddedAtTheCatalogPriceAndReportedAsAChange(): void
    {
        $promotion = $this->factory->coupon();
        $gift = $this->product(['basePrice' => 10.0]);
        $cart = $this->factory->cart();
        $service = $this->service($this->requestStackWithSession());

        $before = time();
        $changed = $service->reconcile([$this->offer($promotion, $gift, 2)], $cart);
        $after = time();

        self::assertTrue($changed);

        $lines = $this->offeredLines($cart);
        self::assertCount(1, $lines);
        self::assertSame($gift->getId(), $lines[0]->getProductId());
        self::assertSame($promotion->getId(), $lines[0]->getOfferedByCouponId());
        self::assertSame(2.0, $lines[0]->getQuantity());
        self::assertSame(10.0, (float) $lines[0]->getPrice(), 'A guest pays the catalog price: no discount applies.');
        self::assertSame(10.0, (float) $lines[0]->getPromoPrice());

        $lifetime = (int) ConfigQuery::read('cart.priceEOF', 60 * 60 * 24 * 30);
        $priceEndOfLife = $lines[0]->getPriceEndOfLife();
        self::assertInstanceOf(\DateTimeInterface::class, $priceEndOfLife);
        self::assertGreaterThanOrEqual($before + $lifetime, $priceEndOfLife->getTimestamp());
        self::assertLessThanOrEqual($after + $lifetime, $priceEndOfLife->getTimestamp());
    }

    public function testReconcilingAnOfferAlreadyGrantedChangesNothing(): void
    {
        $promotion = $this->factory->coupon();
        $gift = $this->product();
        $cart = $this->factory->cart();
        $service = $this->service($this->requestStackWithSession());
        $offer = $this->offer($promotion, $gift, 1);

        self::assertTrue($service->reconcile([$offer], $cart), 'Control: the first run adds the line.');
        $line = $this->offeredLines($cart)[0];

        self::assertFalse($service->reconcile([$offer], $cart));

        $lines = $this->offeredLines($cart);
        self::assertCount(1, $lines);
        self::assertSame($line->getId(), $lines[0]->getId());
        self::assertSame(1.0, $lines[0]->getQuantity());
    }

    public function testAnOfferedLineWhosePinnedPriceExpiredIsRepricedAtTheCatalogPrice(): void
    {
        $promotion = $this->factory->coupon();
        $gift = $this->product(['basePrice' => 10.0]);
        $cart = $this->factory->cart();
        $line = $this->offeredLine($cart, $gift, $promotion, price: '4.000000');
        $line->setPriceEndOfLife(time() - 3600)->save();

        $changed = $this->service($this->requestStackWithSession())->reconcile([$this->offer($promotion, $gift, 1)], $cart);

        self::assertTrue($changed);

        $repriced = $this->offeredLines($cart)[0];
        self::assertSame($line->getId(), $repriced->getId());
        self::assertSame(10.0, (float) $repriced->getPrice());
        self::assertGreaterThan(time(), $repriced->getPriceEndOfLife()?->getTimestamp());
    }

    public function testASupernumeraryOfferedRowIsRemovedAndCountsAsAChange(): void
    {
        $promotion = $this->factory->coupon();
        $gift = $this->product();
        $cart = $this->factory->cart();
        $first = $this->offeredLine($cart, $gift, $promotion);
        $this->offeredLine($cart, $gift, $promotion);

        $changed = $this->service($this->requestStackWithSession())->reconcile([$this->offer($promotion, $gift, 1)], $cart);

        self::assertTrue($changed, 'The kept row needs no update, the removed one is still a change.');
        self::assertSame([$first->getId()], array_map(static fn (CartItem $line): int => $line->getId(), $this->offeredLines($cart)));
    }

    public function testAnOfferedLineNoPromotionAsksForAnyMoreIsRemovedFromTheCartAndItsLoadedLines(): void
    {
        $promotion = $this->factory->coupon();
        $cart = $this->factory->cart();
        $this->offeredLine($cart, $this->product(), $promotion);

        self::assertCount(1, $cart->getCartItems(), 'Control: the cart has its lines loaded.');

        $changed = $this->service($this->requestStackWithSession())->reconcile([], $cart);

        self::assertTrue($changed);
        self::assertSame([], $this->offeredLines($cart));
        self::assertCount(0, $cart->getCartItems(), 'The lines the cart had loaded are read again.');
    }

    public function testAnOfferedLineWhoseStockRanOutIsRemovedAndCountsAsAChange(): void
    {
        $promotion = $this->factory->coupon(['title' => 'Gift of the month']);
        $gift = $this->product(['baseQuantity' => 0]);
        $cart = $this->factory->cart();
        $this->offeredLine($cart, $gift, $promotion);
        $service = $this->service($this->requestStackWithSession());

        $changed = $service->reconcile([$this->offer($promotion, $gift, 1, title: 'Gift of the month')], $cart);

        self::assertTrue($changed);
        self::assertSame([], $this->offeredLines($cart));
        self::assertSame(['Gift of the month'], $service->getUnavailablePromotions());
    }

    public function testAnOutOfStockOfferAddsNothingAndReportsNoChange(): void
    {
        $promotion = $this->factory->coupon(['title' => 'Gift of the month']);
        $gift = $this->product(['baseQuantity' => 0]);
        $cart = $this->factory->cart();
        $service = $this->service($this->requestStackWithSession());

        self::assertFalse($service->reconcile([$this->offer($promotion, $gift, 1, title: 'Gift of the month')], $cart));
        self::assertSame([], $this->offeredLines($cart));
        self::assertSame(['Gift of the month'], $service->getUnavailablePromotions());
    }

    public function testANegativeStockGrantsNothing(): void
    {
        $promotion = $this->factory->coupon(['title' => 'Gift of the month']);
        $gift = $this->product();
        $this->defaultPseFor($gift)->setQuantity(-2)->save();
        $cart = $this->factory->cart();
        $service = $this->service($this->requestStackWithSession());

        self::assertFalse($service->reconcile([$this->offer($promotion, $gift, 1, title: 'Gift of the month')], $cart));
        self::assertSame([], $this->offeredLines($cart));
        self::assertSame(['Gift of the month'], $service->getUnavailablePromotions());
    }

    public function testAVirtualProductIsOfferedWhateverItsStock(): void
    {
        $promotion = $this->factory->coupon();
        $gift = $this->product(['baseQuantity' => 0]);
        $gift->setVirtual(1)->save();
        $cart = $this->factory->cart();
        $service = $this->service($this->requestStackWithSession());

        self::assertTrue($service->reconcile([$this->offer($promotion, $gift, 1)], $cart));
        self::assertCount(1, $this->offeredLines($cart));
        self::assertSame([], $service->getUnavailablePromotions());
    }

    /**
     * @return iterable<string, array{string, float}>
     */
    public static function customerDiscounts(): iterable
    {
        yield 'a customer discount lowers the pinned price' => ['10.000000', 9.0];
        yield 'no customer discount keeps the catalog price' => ['0.000000', 10.0];
        yield 'a negative customer discount never raises the pinned price' => ['-10.000000', 10.0];
    }

    #[DataProvider('customerDiscounts')]
    public function testTheOfferedLineIsPricedWithTheDiscountOfTheCustomer(string $discount, float $expectedPrice): void
    {
        $promotion = $this->factory->coupon();
        $gift = $this->product(['basePrice' => 10.0]);
        $customer = $this->customerWithDiscount($discount);
        $cart = $this->factory->cart($customer);

        $this->service($this->requestStackWithSession())->reconcile([$this->offer($promotion, $gift, 1)], $cart);

        self::assertSame($expectedPrice, (float) $this->offeredLines($cart)[0]->getPrice());
    }

    public function testTheOfferedLineIsPricedWithTheDiscountOfTheGroupOfTheCustomer(): void
    {
        $group = $this->factory->customerGroup('pro', 15.0);
        $customer = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
        $customer->setDiscount('10.000000')->save();
        $gift = $this->product(['basePrice' => 10.0]);
        $cart = $this->factory->cart($customer);

        $this->service($this->requestStackWithSession())->reconcile([$this->offer($this->factory->coupon(), $gift, 1)], $cart);

        // 10, less 15 % for the group, less 10 % for the customer.
        self::assertEqualsWithDelta(7.65, (float) $this->offeredLines($cart)[0]->getPrice(), 0.0000001);
    }

    public function testTheOfferedLineIsPricedAtThePriceTheGroupHasOfItsOwn(): void
    {
        $group = $this->factory->customerGroup('pro', 15.0);
        $customer = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
        $gift = $this->product(['basePrice' => 10.0]);
        $this->factory->customerGroupProductPrice($group, $this->defaultPseFor($gift), $this->factory->currency(), 4.0);
        $cart = $this->factory->cart($customer);

        $this->service($this->requestStackWithSession())->reconcile([$this->offer($this->factory->coupon(), $gift, 1)], $cart);

        self::assertSame(4.0, (float) $this->offeredLines($cart)[0]->getPrice());
    }

    public function testAGiftNothingPricesIsNotOffered(): void
    {
        $gift = $this->product(['basePrice' => 10.0]);
        ProductPriceQuery::create()->filterByProductSaleElementsId($this->defaultPseFor($gift)->getId())->delete();
        $cart = $this->factory->cart();

        $changed = $this->service($this->requestStackWithSession())->reconcile([$this->offer($this->factory->coupon(), $gift, 1)], $cart);

        self::assertFalse($changed);
        self::assertSame([], $this->offeredLines($cart));
    }

    public function testTheOfferedLineIsPricedInTheCurrencyOfTheCart(): void
    {
        $promotion = $this->factory->coupon();
        $gift = $this->product(['basePrice' => 10.0]);
        $cartCurrency = $this->factory->currency(['code' => 'XTS', 'rate' => 3.0]);
        $this->factory->productPrice($this->defaultPseFor($gift), $cartCurrency, ['price' => '25.000000', 'promoPrice' => '25.000000']);
        $cart = $this->factory->cart(null, ['currency' => $cartCurrency]);

        self::assertNotSame(Currency::getDefaultCurrency()->getId(), $cartCurrency->getId(), 'Control: the cart is not in the default currency.');

        $this->service($this->requestStackWithSession())->reconcile([$this->offer($promotion, $gift, 1)], $cart);

        self::assertSame(25.0, (float) $this->offeredLines($cart)[0]->getPrice(), 'The price stored for the currency, not one converted from the default currency.');
    }

    public function testAReconciliationThatFailedDoesNotBlockTheNextOne(): void
    {
        $promotion = $this->factory->coupon();
        $gift = $this->product();
        $cart = $this->factory->cart();
        $service = $this->service($this->requestStackWithSession());

        $failing = $this->createStubForIntersectionOfInterfaces([CouponInterface::class, OfferedLineProviderInterface::class]);
        $failing->method('getOfferedLineRequests')->willThrowException(new \RuntimeException('The offer could not be computed.'));

        try {
            $service->reconcile([$failing], $cart);
            self::fail('The failure of the offer is expected to propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame('The offer could not be computed.', $exception->getMessage());
        }

        self::assertTrue($service->reconcile([$this->offer($promotion, $gift, 1)], $cart));
        self::assertCount(1, $this->offeredLines($cart));
    }

    public function testAReconciliationReEnteredWhileRunningLeavesTheWorkToTheOuterOne(): void
    {
        $promotion = $this->factory->coupon();
        $gift = $this->product();
        $otherGift = $this->product();
        $cart = $this->factory->cart();
        $service = $this->service($this->requestStackWithSession());
        $innerOffer = $this->offer($promotion, $otherGift, 1);

        $innerResult = null;
        $outerOffer = $this->createStubForIntersectionOfInterfaces([CouponInterface::class, OfferedLineProviderInterface::class]);
        $outerOffer->method('getTitle')->willReturn('Outer offer');
        $outerOffer->method('getOfferedLineRequests')->willReturnCallback(
            static function () use ($service, $innerOffer, $cart, $gift, $promotion, &$innerResult): array {
                $innerResult = $service->reconcile([$innerOffer], $cart);

                return [new OfferedLineRequest($gift->getId(), 1, $promotion->getId())];
            },
        );

        self::assertTrue($service->reconcile([$outerOffer], $cart));
        self::assertFalse($innerResult, 'The re-entered run returns at once, reporting no change.');
        self::assertSame([$gift->getId()], array_map(static fn (CartItem $line): int => $line->getProductId(), $this->offeredLines($cart)));
    }

    public function testThePromotionsLeftUngrantedAreStoredInTheSessionAsAList(): void
    {
        $promotion = $this->factory->coupon(['title' => 'Gift of the month']);
        $gift = $this->product(['baseQuantity' => 0]);
        $requestStack = $this->requestStackWithSession();

        $this->service($requestStack)->reconcile([$this->offer($promotion, $gift, 1, title: 'Gift of the month')], $this->factory->cart());

        self::assertSame(
            ['Gift of the month'],
            $requestStack->getMainRequest()?->getSession()->get(OfferedCartLineService::UNAVAILABLE_PROMOTIONS_SESSION_KEY),
        );
    }

    public function testWithinTheRequestThatReconciledTheInMemoryStateAnswersWhateverTheSessionHolds(): void
    {
        $promotion = $this->factory->coupon(['title' => 'Gift of the month']);
        $gift = $this->product(['baseQuantity' => 0]);
        $requestStack = $this->requestStackWithSession();
        $service = $this->service($requestStack);

        $service->reconcile([$this->offer($promotion, $gift, 1, title: 'Gift of the month')], $this->factory->cart());

        // Another request of the same visitor wrote the session in between.
        $requestStack->getMainRequest()?->getSession()->set(OfferedCartLineService::UNAVAILABLE_PROMOTIONS_SESSION_KEY, ['Another promotion']);

        self::assertSame(['Gift of the month'], $service->getUnavailablePromotions());
    }

    public function testOnlyTheLabelsAreReadBackFromTheSession(): void
    {
        $requestStack = $this->requestStackWithSession();
        $requestStack->getMainRequest()?->getSession()->set(
            OfferedCartLineService::UNAVAILABLE_PROMOTIONS_SESSION_KEY,
            [3, 'Gift of the month', null, 'Second gift'],
        );

        self::assertSame(['Gift of the month', 'Second gift'], $this->service($requestStack)->getUnavailablePromotions());
    }

    /**
     * @return iterable<string, array{\Closure(): RequestStack}>
     */
    public static function requestStacksWithoutSession(): iterable
    {
        yield 'no request at all (CLI)' => [static fn (): RequestStack => new RequestStack()];
        yield 'a request without a session' => [static function (): RequestStack {
            $requestStack = new RequestStack();
            $requestStack->push(Request::create('http://localhost'));

            return $requestStack;
        }];
    }

    /**
     * @param \Closure(): RequestStack $requestStack
     */
    #[DataProvider('requestStacksWithoutSession')]
    public function testWithoutASessionThePromotionIsNamedAfterItsOwnTitleOrElseItsCode(\Closure $requestStack): void
    {
        $titled = $this->factory->coupon();
        $untitled = $this->factory->coupon();
        $gift = $this->product(['baseQuantity' => 0]);
        $service = $this->service($requestStack());

        $service->reconcile(
            [
                $this->offer($titled, $gift, 1, title: 'Spring gift', code: 'SPRING'),
                $this->offer($untitled, $gift, 1, title: '', code: 'SUMMER'),
            ],
            $this->factory->cart(),
        );

        self::assertSame(['Spring gift', 'SUMMER'], $service->getUnavailablePromotions());
    }

    public function testAPromotionUntranslatedInTheLanguageOfTheSessionIsNamedAfterItsOwnTitle(): void
    {
        $promotion = $this->factory->coupon(['title' => 'Gift of the month']);
        $gift = $this->product(['baseQuantity' => 0]);
        $requestStack = $this->requestStackWithSession();
        $session = $requestStack->getMainRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);
        $session->setLang((new Lang())->setLocale('xx_XX'));
        $service = $this->service($requestStack);

        $service->reconcile([$this->offer($promotion, $gift, 1, title: 'Fallback title')], $this->factory->cart());

        self::assertSame(['Fallback title'], $service->getUnavailablePromotions());
    }

    public function testThePromotionIsNamedAfterItsTitleInTheLanguageOfTheSession(): void
    {
        $promotion = $this->factory->coupon(['locale' => 'fr_FR', 'title' => 'Cadeau du mois']);
        $gift = $this->product(['baseQuantity' => 0]);
        $requestStack = $this->requestStackWithSession();
        $session = $requestStack->getMainRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);
        $session->setLang((new Lang())->setLocale('fr_FR'));
        $service = $this->service($requestStack);

        $service->reconcile([$this->offer($promotion, $gift, 1, title: 'Gift of the month')], $this->factory->cart());

        self::assertSame(['Cadeau du mois'], $service->getUnavailablePromotions());
    }

    public function testAGrantedLineFollowsTheRequestedQuantityUpToTheStock(): void
    {
        $promotion = $this->factory->coupon();
        $gift = $this->product();
        $this->defaultPseFor($gift)->setQuantity(2)->save();
        $cart = $this->factory->cart();
        $line = $this->offeredLine($cart, $gift, $promotion);

        $changed = $this->service($this->requestStackWithSession())->reconcile([$this->offer($promotion, $gift, 3)], $cart);

        self::assertTrue($changed);
        $lines = $this->offeredLines($cart);
        self::assertCount(1, $lines);
        self::assertSame($line->getId(), $lines[0]->getId());
        self::assertSame(2.0, $lines[0]->getQuantity());
    }

    public function testTwoRequestsOfTheSamePromotionForTheSameProductAddUp(): void
    {
        $promotion = $this->factory->coupon();
        $gift = $this->product();
        $cart = $this->factory->cart();
        $offer = $this->createStubForIntersectionOfInterfaces([CouponInterface::class, OfferedLineProviderInterface::class]);
        $offer->method('getTitle')->willReturn('Offer');
        $offer->method('getOfferedLineRequests')->willReturn([
            new OfferedLineRequest($gift->getId(), 1, $promotion->getId()),
            new OfferedLineRequest($gift->getId(), 2, $promotion->getId()),
        ]);

        $this->service($this->requestStackWithSession())->reconcile([$offer], $cart);

        $lines = $this->offeredLines($cart);
        self::assertCount(1, $lines);
        self::assertSame(3.0, $lines[0]->getQuantity());
    }

    public function testTheOfferedLineCarriesThePromoPriceOfTheCatalog(): void
    {
        $promotion = $this->factory->coupon();
        $gift = $this->product(['basePrice' => 10.0]);
        $productSaleElements = $this->defaultPseFor($gift);
        $productSaleElements->setPromo(1)->save();
        $catalogPrice = ProductPriceQuery::create()
            ->filterByProductSaleElementsId($productSaleElements->getId())
            ->findOne();
        self::assertNotNull($catalogPrice);
        $catalogPrice->setPromoPrice('8.000000')->save();
        $cart = $this->factory->cart(null, ['currency' => $catalogPrice->getCurrency()]);

        $this->service($this->requestStackWithSession())->reconcile([$this->offer($promotion, $gift, 1)], $cart);

        $line = $this->offeredLines($cart)[0];
        self::assertSame(10.0, (float) $line->getPrice());
        self::assertSame(8.0, (float) $line->getPromoPrice());
        self::assertSame(1, $line->getPromo());
    }

    public function testAnOfferedLineWithoutPriceEndOfLifeKeepsItsPrice(): void
    {
        $promotion = $this->factory->coupon();
        $gift = $this->product(['basePrice' => 10.0]);
        $cart = $this->factory->cart();
        $this->offeredLine($cart, $gift, $promotion, price: '4.000000');

        self::assertFalse($this->service($this->requestStackWithSession())->reconcile([$this->offer($promotion, $gift, 1)], $cart));
        self::assertSame(4.0, (float) $this->offeredLines($cart)[0]->getPrice());
    }

    public function testAfterAResetTheUngrantedPromotionsAreReadBackFromTheSession(): void
    {
        $promotion = $this->factory->coupon(['title' => 'Gift of the month']);
        $gift = $this->product(['baseQuantity' => 0]);
        $service = $this->service($this->requestStackWithSession());
        $service->reconcile([$this->offer($promotion, $gift, 1, title: 'Gift of the month')], $this->factory->cart());

        $service->reset();

        self::assertSame(['Gift of the month'], $service->getUnavailablePromotions());
    }

    public function testWithoutASessionAResetForgetsTheUngrantedPromotions(): void
    {
        $promotion = $this->factory->coupon();
        $gift = $this->product(['baseQuantity' => 0]);
        $service = $this->service(new RequestStack());
        $service->reconcile([$this->offer($promotion, $gift, 1, title: 'Gift of the month')], $this->factory->cart());
        self::assertSame(['Gift of the month'], $service->getUnavailablePromotions(), 'Control: the shortage is known.');

        $service->reset();

        self::assertSame([], $service->getUnavailablePromotions());
    }

    public function testASessionValueThatIsNotAListReadsAsNoPromotion(): void
    {
        $requestStack = $this->requestStackWithSession();
        $requestStack->getMainRequest()?->getSession()->set(OfferedCartLineService::UNAVAILABLE_PROMOTIONS_SESSION_KEY, 'Gift of the month');

        self::assertSame([], $this->service($requestStack)->getUnavailablePromotions());
    }

    public function testWhenTheStockIsNotCheckedTheWholeRequestIsGranted(): void
    {
        ConfigQuery::write('check-available-stock', '0');
        $promotion = $this->factory->coupon();
        $gift = $this->product(['baseQuantity' => 0]);
        $cart = $this->factory->cart();
        $service = $this->service($this->requestStackWithSession());

        self::assertTrue($service->reconcile([$this->offer($promotion, $gift, 2)], $cart));
        self::assertSame(2.0, $this->offeredLines($cart)[0]->getQuantity());
        self::assertSame([], $service->getUnavailablePromotions());
    }

    public function testACouponThatOffersNothingDoesNotStopTheOffersKeptAfterIt(): void
    {
        $promotion = $this->factory->coupon();
        $gift = $this->product();
        $cart = $this->factory->cart();
        $plainCoupon = $this->createStub(CouponInterface::class);

        self::assertTrue($this->service($this->requestStackWithSession())->reconcile([$plainCoupon, $this->offer($promotion, $gift, 1)], $cart));
        self::assertCount(1, $this->offeredLines($cart));
    }

    public function testAnOfferOfAProductThatNoLongerExistsAddsNothing(): void
    {
        $promotion = $this->factory->coupon();
        $cart = $this->factory->cart();
        $missingProduct = (new Product())->setId((int) ProductQuery::create()->orderById(Criteria::DESC)->findOne()?->getId() + 1000);
        $service = $this->service($this->requestStackWithSession());

        self::assertFalse($service->reconcile([$this->offer($promotion, $missingProduct, 1)], $cart));
        self::assertSame([], $this->offeredLines($cart));
        self::assertSame([], $service->getUnavailablePromotions(), 'A product gone is not a shortage to announce.');
    }

    private function service(RequestStack $requestStack): OfferedCartLineService
    {
        return new OfferedCartLineService($this->createStub(FacadeInterface::class), $requestStack, $this->getService(CartLinePricer::class));
    }

    private function requestStackWithSession(): RequestStack
    {
        $request = Request::create('http://localhost');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return $requestStack;
    }

    private function offer(
        Coupon $promotion,
        Product $gift,
        int $quantity,
        string $title = 'Offer',
        string $code = 'OFFER',
    ): CouponInterface&OfferedLineProviderInterface {
        $offer = $this->createStubForIntersectionOfInterfaces([CouponInterface::class, OfferedLineProviderInterface::class]);
        $offer->method('getOfferedLineRequests')->willReturn([
            new OfferedLineRequest($gift->getId(), $quantity, $promotion->getId()),
        ]);
        $offer->method('getTitle')->willReturn($title);
        $offer->method('getCode')->willReturn($code);

        return $offer;
    }

    private function offeredLine(Cart $cart, Product $gift, Coupon $promotion, string $price = '10.000000'): CartItem
    {
        return $this->factory->cartItem($cart, $gift, $this->defaultPseFor($gift), [
            'quantity' => 1.0,
            'price' => $price,
            'promoPrice' => $price,
            'isOffered' => 1,
            'offeredByCouponId' => $promotion->getId(),
        ]);
    }

    private function customerWithDiscount(string $discount): Customer
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $customer->setDiscount($discount)->save();

        return $customer;
    }

    /**
     * @return list<CartItem>
     */
    private function offeredLines(Cart $cart): array
    {
        return iterator_to_array(
            CartItemQuery::create()
                ->filterByCartId($cart->getId())
                ->filterByIsOffered(1)
                ->orderById()
                ->find(),
            false,
        );
    }
}
