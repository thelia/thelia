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

namespace Thelia\Tests\Http\Flexy;

use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Checkout\Enum\GuestCheckoutMode;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Map\CartTableMap;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * A pack of the cart the catalogue changed under (a required component deleted after it was added): the cart
 * step refuses the cart, and the buyer sent back to it from the delivery or the payment step is told why, in the
 * language of the theme, instead of being sent back by a button that seems to do nothing.
 *
 * The cart page itself says it on the line of the pack (CartBundleLinesTest of the theme).
 */
final class CheckoutBundleRefusalTest extends GuestCheckoutTestCase
{
    use BuildsBundles;

    private const SENTENCE = 'can no longer be ordered as it is in your cart. Please remove it and add it again.';

    protected function setUp(): void
    {
        if (!class_exists(\FlexyBundle\Service\BundleLines::class)) {
            self::markTestSkipped('The installed front-office theme does not know the product bundles.');
        }

        parent::setUp();
    }

    public function testTheDeliveryStepSendsTheBuyerBackToTheCartAndSaysWhy(): void
    {
        [, $example] = $this->aGuestWhoseCartHoldsAPackThatCanNoLongerBeOrdered();

        $this->client->request('GET', '/checkout/delivery');

        $this->assertResponseRedirectsTo('/checkout/cart');
        $landed = $this->client->followRedirect();

        $this->assertTheBuyerIsToldOnTheFlashAndOnTheLine((string) $example['bundle']->getRef(), $landed->text());
    }

    public function testThePaymentStepSendsTheBuyerBackToTheCartAndSaysWhy(): void
    {
        [, $example] = $this->aGuestWhoseCartHoldsAPackThatCanNoLongerBeOrdered();

        $this->client->request('GET', '/checkout/payment');

        $this->assertResponseRedirectsTo('/checkout/cart');
        $landed = $this->client->followRedirect();

        $this->assertTheBuyerIsToldOnTheFlashAndOnTheLine((string) $example['bundle']->getRef(), $landed->text());
    }

    public function testTheReasonIsFlashedOnceAndTheLineOfThePackKeepsSayingIt(): void
    {
        $this->aGuestWhoseCartHoldsAPackThatCanNoLongerBeOrdered();
        $this->client->request('GET', '/checkout/delivery');
        $landed = $this->client->followRedirect();

        // The flash of the redirection and the line of the pack in the cart.
        self::assertSame(2, substr_count($landed->text(), self::SENTENCE));

        $again = $this->client->request('GET', '/checkout/cart');

        self::assertSame(1, substr_count($again->text(), self::SENTENCE), 'The flash is gone, the line still says it.');
    }

    public function testACartThatHoldsNoSuchPackIsSentBackWithoutAnyWordAboutPacks(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::EnabledUnlessProductForbids);
        $this->openASessionWithACart();
        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));
        $this->assertResponseRedirectsTo('/checkout/delivery');

        // The delivery step is the first one the buyer has not settled: the payment sends them back to it.
        $this->client->request('GET', '/checkout/payment');
        $landed = $this->client->followRedirect();

        self::assertStringNotContainsString(self::SENTENCE, $landed->text());
        self::assertCount(0, $landed->filter('.SnackBar--error'), 'A cart without a pack is sent back in silence.');
    }

    public function testAnEmptyCartIsSentBackWithoutAnyFlash(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::EnabledUnlessProductForbids);
        $cart = $this->openASessionWithACart();
        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));
        $this->assertResponseRedirectsTo('/checkout/delivery');
        CartItemQuery::create()->filterByCartId($cart->getId())->delete();

        $this->client->request('GET', '/checkout/payment');
        $landed = $this->client->followRedirect();

        self::assertStringNotContainsString(self::SENTENCE, $landed->text());
        self::assertCount(0, $landed->filter('.SnackBar--error'), 'The empty cart says so on its own screen, not by a flash.');
    }

    /**
     * Twice: the flash of the redirection, and the line of the pack in the cart that says it by itself.
     */
    private function assertTheBuyerIsToldOnTheFlashAndOnTheLine(string $reference, string $html): void
    {
        self::assertStringContainsString(\sprintf('The pack %s %s', $reference, self::SENTENCE), $html);
        self::assertSame(2, substr_count($html, self::SENTENCE), 'The redirection says why, and so does the line.');
    }

    /**
     * @return array{0: Cart, 1: array<string, mixed>}
     */
    private function aGuestWhoseCartHoldsAPackThatCanNoLongerBeOrdered(): array
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::EnabledUnlessProductForbids);

        $cart = $this->openASessionWithACart();
        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));
        $this->assertResponseRedirectsTo('/checkout/delivery');

        $example = $this->exampleBundle($this->fixtures(), $this->getService(BundleCompositionService::class));

        (new CartItem())
            ->setCartId($cart->getId())
            ->setProductId($example['bundle']->getId())
            ->setProductSaleElementsId($this->saleElementsOf($example['bundle'])->getId())
            ->setQuantity(1)
            ->setPrice('600.000000')
            ->setPromoPrice('540.000000')
            ->setPromo(1)
            ->setIsOffered(0)
            ->setBundleComposition(BundleComposition::fromArray([
                ['bundleItemId' => $example['bike']->getId(), 'quantity' => 1],
                ['bundleItemId' => $example['helmet']->getId(), 'quantity' => 2],
            ])->toCanonicalJson())
            ->save();

        // The helmet is required: without its product the pack is no longer what the line stored.
        $example['helmetProduct']->delete();

        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();

        return [$cart, $example];
    }
}
