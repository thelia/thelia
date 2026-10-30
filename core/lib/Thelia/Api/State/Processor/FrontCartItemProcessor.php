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

namespace Thelia\Api\State\Processor;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use Propel\Runtime\Propel;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\CartItem as CartItemResource;
use Thelia\Api\Security\CartOwnership;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Bundle\CartCompositionResolver;
use Thelia\Domain\Cart\Bundle\CartLineAvailability;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Cart\DTO\CartItemDeleteDTO;
use Thelia\Domain\Cart\DTO\CartItemUpdateQuantityDTO;
use Thelia\Domain\Cart\Exception\InvalidCartException;
use Thelia\Domain\Cart\Exception\NotEnoughStockException;
use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\Catalog\Quantity\InvalidQuantityRuleException;
use Thelia\Domain\Catalog\Quantity\NotPositiveQuantityException;
use Thelia\Domain\Catalog\Quantity\QuantityPolicy;
use Thelia\Domain\Catalog\Quantity\QuantityRefusedException;
use Thelia\Domain\Catalog\Quantity\QuantityScale;
use Thelia\Domain\Catalog\Quantity\UnreadableQuantityException;
use Thelia\Domain\Catalog\Stock\StockReservationService;
use Thelia\Domain\Sale\ReservedSaleVisibility;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * The front writes of a cart line go through the cart, the way the theme writes
 * them: CartFacade dispatches CART_ADDITEM, CART_UPDATEITEM and CART_DELETEITEM,
 * where the shop prices the line and where a module refuses a quantity or a
 * product. Writing the row straight into the table skipped all of it.
 *
 * The stock is the cart's to check on a change (CartItem::updateQuantity). On an
 * addition the cart checks it only when the line exists already, and then keeps
 * the quantity it had without a word, so it is checked here before the addition
 * and on the line once the cart is done. The catalogue
 * the addition reads from is the one the shop shows.
 *
 * Nothing about money is taken from the body: an addition names a cart, a sale
 * element and a quantity - and, for a bundle, the composition the customer chose,
 * revalidated against the bundle - a change names a quantity, and the price comes
 * from the catalogue. A refusal of the cart is the caller's business, answered with a 422.
 */
final readonly class FrontCartItemProcessor implements ProcessorInterface
{
    public function __construct(
        private CartFacade $cartFacade,
        private CartOwnership $cartOwnership,
        private ReservedSaleVisibility $reservedSaleVisibility,
        private ApiResourcePropelTransformerService $transformer,
        private EventDispatcherInterface $dispatcher,
        private CartLineAvailability $cartLineAvailability,
        private CartCompositionResolver $cartCompositionResolver,
        private ?StockReservationService $stockReservation = null,
        private QuantityPolicy $quantityPolicy = new QuantityPolicy(),
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?CartItemResource
    {
        if (!$data instanceof CartItemResource) {
            throw new UnprocessableEntityHttpException('Unsupported cart item write.');
        }

        // The cart writes the line before every listener has had its say: one that
        // refuses afterwards must leave the cart as it was, as the ordering by
        // reference does (QuickOrderFacade::addToCart).
        $connection = Propel::getWriteConnection(CartTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $cartItem = match (true) {
                $operation instanceof Post => $this->add($data),
                $operation instanceof Put => $this->changeQuantity($this->ownedLine($uriVariables), $data),
                $operation instanceof Delete => $this->remove($this->ownedLine($uriVariables)),
                default => throw new UnprocessableEntityHttpException('Unsupported cart item write.'),
            };

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            if ($throwable instanceof InvalidCartException || $throwable instanceof NotEnoughStockException) {
                throw new UnprocessableEntityHttpException($throwable->getMessage(), $throwable);
            }

            throw $throwable;
        }

        if (null === $cartItem) {
            return null;
        }

        // Once the line is committed, in a transaction of its own: held inside the one above, the
        // hold would wait on the stock row the write of the line shares with another buyer.
        $this->stockReservation?->holdAfterCartChange($cartItem->getCart());

        return $this->transformer->modelToResource(
            resourceClass: CartItemResource::class,
            propelModel: $cartItem,
            context: $operation->getNormalizationContext() ?? [],
        );
    }

    /**
     * The cart is named by the caller, who may have more than one, and must be theirs
     * under the rule that scopes every front cart read: a cart of somebody else is
     * answered the way the front cart answers one it cannot find. An IRI that leads
     * nowhere never gets this far: resolving it answers 400.
     */
    private function add(CartItemResource $data): CartItem
    {
        if (!isset($data->cart)) {
            throw new UnprocessableEntityHttpException('A cart line needs a cart.');
        }

        $cart = CartQuery::create()->findPk($data->cart->getId());

        if (null === $cart || !$this->cartOwnership->ownsCart($cart->getCustomerId(), $cart->getId())) {
            throw new NotFoundHttpException('Cart not found.');
        }

        $saleElements = isset($data->productSaleElements)
            ? $this->saleElementsOnSale((int) $data->productSaleElements->getId())
            : null;

        if (null === $saleElements) {
            throw new UnprocessableEntityHttpException('A cart line needs a product sale element.');
        }

        $composition = $this->compositionOf($saleElements, $data);
        $joined = $this->lineTheCartJoins($cart, $saleElements, $composition);
        $inLine = null === $joined ? 0.0 : (float) $joined->getQuantity();
        $quantity = self::positiveQuantity($data->quantity, $inLine);
        $this->assertTheRulesAccept($inLine + $quantity, $saleElements);

        // The stock of a bundle is judged once, by the guard of the addition, from the composition
        // handed over below.
        if (null === $composition) {
            $this->assertStockFor($saleElements, $inLine + $quantity, $cart);
        }

        $before = $this->quantitiesOfTheLines((int) $cart->getId());

        $cartItem = $this->cartFacade->addItem(new CartItemAddDTO(
            $cart,
            (int) $saleElements->getProductId(),
            (int) $saleElements->getId(),
            $quantity,
            bundleComposition: $composition,
        ));

        // The caller asked for a quantity and gets it whole or not at all: the cart keeps
        // the quantity a line had, without a word, when the stock falls short
        // (CartItem::addQuantity), and a module ahead of it may take less than was asked.
        // The line is compared with itself, as the ordering by reference does, in
        // millionths: a quantity with decimals is never compared as a float.
        $taken = (float) $cartItem->getQuantity() - ($before[(int) $cartItem->getId()] ?? 0.0);

        if (QuantityScale::micro($taken) < QuantityScale::micro($quantity)) {
            throw new InvalidCartException('The cart did not take the whole quantity.');
        }

        return $cartItem;
    }

    /**
     * What the caller proposes for a bundle, revalidated against the bundle, or null for a
     * product that is not one: a composition proposed for such a product is refused, and a
     * bundle without composition takes its default one. A refusal is a 422.
     *
     * @throws InvalidBundleCompositionException
     */
    private function compositionOf(ProductSaleElements $saleElements, CartItemResource $data): ?BundleComposition
    {
        $proposed = null === $data->bundleComposition ? null : BundleComposition::fromArray($data->bundleComposition);

        return $this->cartCompositionResolver->resolveAndHandOver((int) $saleElements->getProductId(), $proposed);
    }

    /**
     * Checked before the addition on what the line will hold once the addition
     * joins it, the way CartItem::addQuantity checks it, and refused out loud. The
     * CartAdd form only compares the quantity added. How many the shop can supply is
     * decided in one place (CartLineAvailability); the lines of a bundle are judged by the guard
     * of the addition, which holds the composition.
     */
    private function assertStockFor(ProductSaleElements $saleElements, float $quantity, Cart $cart): void
    {
        if (!ConfigQuery::checkAvailableStock()) {
            return;
        }

        if ($this->cartLineAvailability->forLine($saleElements, null, $cart) < $quantity) {
            throw self::notEnoughStockFor($saleElements);
        }
    }

    /**
     * The line the addition will join, asked of the cart the way the cart asks it
     * (CART_FINDITEM), so that a module picking the line is heard here too.
     */
    private function lineTheCartJoins(Cart $cart, ProductSaleElements $saleElements, ?BundleComposition $composition): ?CartItem
    {
        $event = (new CartEvent($cart))
            ->setProductId((int) $saleElements->getProductId())
            ->setProductSaleElementsId((int) $saleElements->getId())
            ->setBundleComposition($composition);

        $this->dispatcher->dispatch($event, TheliaEvents::CART_FINDITEM);

        return $event->getCartItem();
    }

    /**
     * @return array<int, float> the quantity of every line of the cart, by line id
     */
    private function quantitiesOfTheLines(int $cartId): array
    {
        $quantities = [];

        foreach (CartItemQuery::create()->filterByCartId($cartId)->find() as $line) {
            $quantities[(int) $line->getId()] = (float) $line->getQuantity();
        }

        return $quantities;
    }

    private static function notEnoughStockFor(ProductSaleElements $saleElements): NotEnoughStockException
    {
        return new NotEnoughStockException(\sprintf('Not enough stock for product %s', $saleElements->getProduct()->getRef()));
    }

    /**
     * The sale element as the catalogue offers it to this caller, the way the
     * ordering by reference reads it (ReferenceResolver): its product online, the
     * sale element itself online, and out of a private drop that does not name
     * the caller. Whatever the request named it by (front IRI, admin IRI, bare
     * id), one that is hidden answers like one that does not exist.
     */
    private function saleElementsOnSale(int $saleElementsId): ?ProductSaleElements
    {
        $query = ProductSaleElementsQuery::create()
            ->filterById($saleElementsId)
            ->filterByVisible(true)
            ->useProductQuery()
                ->filterByVisible(true)
            ->endUse();

        $this->reservedSaleVisibility->applyTo($query, ProductSaleElementsTableMap::COL_PRODUCT_ID);

        return $query->findOne();
    }

    /**
     * A line changes its quantity only: another sale element is another line, added
     * through the cart and priced by it.
     */
    private function changeQuantity(CartItem $cartItem, CartItemResource $data): CartItem
    {
        if (isset($data->productSaleElements) && $data->productSaleElements->getId() !== (int) $cartItem->getProductSaleElementsId()) {
            throw new UnprocessableEntityHttpException('A cart line changes its quantity only.');
        }

        // The composition is the one of the line: another choice is another line, added through the cart.
        if (null !== $data->bundleComposition
            && BundleComposition::fromArray($data->bundleComposition)->toCanonicalJson() !== $cartItem->getBundleComposition()) {
            throw new UnprocessableEntityHttpException('A cart line changes its quantity only.');
        }

        $quantity = self::positiveQuantity($data->quantity);
        $saleElements = $cartItem->getProductSaleElements();

        if (null !== $saleElements) {
            $this->assertTheRulesAccept($quantity, $saleElements);
        }

        return $this->cartFacade->updateItemQuantity(new CartItemUpdateQuantityDTO(
            $cartItem->getCart(),
            (int) $cartItem->getId(),
            $quantity,
        ));
    }

    private function remove(CartItem $cartItem): null
    {
        $this->cartFacade->removeItem(new CartItemDeleteDTO($cartItem->getCart(), (int) $cartItem->getId()));

        return null;
    }

    /**
     * The line the provider read and the voter let through for this caller.
     *
     * @param array<string, mixed> $uriVariables
     */
    private function ownedLine(array $uriVariables): CartItem
    {
        return CartItemQuery::create()->findPk((int) ($uriVariables['id'] ?? 0))
            ?? throw new NotFoundHttpException('Cart item not found.');
    }

    /**
     * Zero or less is no quantity, and the line the quantity leads to is held to the
     * ceiling the ordering by reference sets (ReferenceQuantityLines::MAX_QUANTITY), which
     * also turns away the INF a JSON number beyond a float decodes to. Whether the
     * quantity may carry decimals, and which ones, is for the rules of the combination.
     */
    private static function positiveQuantity(?float $quantity, float $alreadyInLine = 0.0): float
    {
        if (null === $quantity || is_nan($quantity) || $quantity <= 0) {
            throw new UnprocessableEntityHttpException('The quantity must be greater than zero.');
        }

        if ($alreadyInLine + $quantity > ReferenceQuantityLines::MAX_QUANTITY) {
            throw new UnprocessableEntityHttpException(\sprintf('A cart line holds at most %d units.', ReferenceQuantityLines::MAX_QUANTITY));
        }

        return $quantity;
    }

    /**
     * The quantity the line will hold, judged by the rules of its combination: its
     * minimum, its step and its decimals. A refusal says which quantities are sold.
     */
    private function assertTheRulesAccept(float $quantity, ProductSaleElements $saleElements): void
    {
        try {
            $this->quantityPolicy->assertAcceptable($quantity, $saleElements);
        } catch (QuantityRefusedException|NotPositiveQuantityException|UnreadableQuantityException|InvalidQuantityRuleException $refusal) {
            throw new UnprocessableEntityHttpException($refusal->getMessage(), $refusal);
        }
    }
}
