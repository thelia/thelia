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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\Cart as CartResource;
use Thelia\Api\Resource\Quote as QuoteResource;
use Thelia\Api\Service\Quote\QuoteReach;
use Thelia\Api\Service\Quote\QuoteRequestBody;
use Thelia\Api\Service\Quote\QuoteWriteLimiter;
use Thelia\Domain\Quote\Cart\QuoteCartSwitcher;
use Thelia\Domain\Quote\DTO\QuoteItemRequestInput;
use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Domain\Quote\Exception\QuoteRequestRefusedException;
use Thelia\Domain\Quote\QuoteFacade;
use Thelia\Model\CartQuery;
use Thelia\Model\Customer;
use Thelia\Model\Quote as QuoteModel;

/**
 * What the account of the storefront does with a quote: asks for one from a cart, counter-proposes,
 * accepts or turns down the revision the quote stands at.
 *
 * Every write goes to the quote facade, which judges it: the state of the quote, the revision, the
 * right of the account, the lines. This only reads the body, field by field, reaches the quote under
 * the rule the reads obey (a quote out of reach is a 404, one the role does not allow to answer a
 * 403), spends the budget of the account where there is one, and answers the quote as the storefront
 * reads it. The facade's refusals reach the error mapping of the API (api_platform.php) unchanged.
 *
 * @implements ProcessorInterface<mixed, QuoteResource|CartResource>
 */
final readonly class QuoteFrontProcessor implements ProcessorInterface
{
    private const int MAXIMUM_COUNTER_OFFER_LINES = 500;

    public function __construct(
        private QuoteFacade $quotes,
        private QuoteReach $reach,
        private QuoteWriteLimiter $limiter,
        private QuoteCartSwitcher $cartSwitcher,
        private ApiResourcePropelTransformerService $transformer,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): QuoteResource|CartResource
    {
        $customer = QuoteResource::OPERATION_FRONT_REQUEST === $operation->getName() ? $this->reach->requestor() : $this->reach->customer();

        return match ($operation->getName()) {
            QuoteResource::OPERATION_FRONT_REQUEST => $this->request($customer, $operation, $context),
            QuoteResource::OPERATION_FRONT_COUNTER_OFFER => $this->counterOffer($customer, $operation, $uriVariables, $context),
            QuoteResource::OPERATION_FRONT_ACCEPT => $this->accept($customer, $operation, $uriVariables, $context),
            QuoteResource::OPERATION_FRONT_REJECT => $this->reject($customer, $operation, $uriVariables, $context),
            default => throw new \LogicException(\sprintf('The quote processor of the storefront does not handle the operation "%s".', (string) $operation->getName())),
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    private function request(Customer $customer, Operation $operation, array $context): QuoteResource
    {
        $body = QuoteRequestBody::of($this->requestStack->getCurrentRequest(), ['cartId', 'comment', 'neededBy', 'invoiceAddressId', 'deliveryAddressId']);
        $cartId = $body->integer('cartId');
        $input = new QuoteRequestInput(
            $body->text('comment'),
            $body->day('neededBy'),
            $body->integer('invoiceAddressId', false),
            $body->integer('deliveryAddressId', false),
        );

        $this->limiter->spendRequest((int) $customer->getId());

        // A cart that does not exist and a cart of somebody else are the same answer.
        $cart = null === $cartId || $cartId < 1 ? null : CartQuery::create()->findPk($cartId);

        if (null === $cart) {
            throw QuoteRequestRefusedException::cartNotOwned();
        }

        return $this->quoteResource($this->quotes->requestFromCart($cart, $customer, $input), $operation, $context);
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    private function counterOffer(Customer $customer, Operation $operation, array $uriVariables, array $context): QuoteResource
    {
        $quote = $this->reach->answerable($customer, $uriVariables);
        $body = QuoteRequestBody::of($this->requestStack->getCurrentRequest(), ['revision', 'lines']);
        $revision = $body->integer('revision');
        $requests = [];

        foreach ($body->objects('lines', ['quoteItemId', 'quantity', 'price'], self::MAXIMUM_COUNTER_OFFER_LINES) as $line) {
            $requests[] = new QuoteItemRequestInput(
                (int) $line->integer('quoteItemId'),
                $line->number('quantity', false),
                $line->amount('price', false),
            );
        }

        $this->limiter->spendCounterOffer((int) $customer->getId());

        return $this->quoteResource($this->quotes->counterOffer($quote, $customer, (int) $revision, $requests), $operation, $context);
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    private function accept(Customer $customer, Operation $operation, array $uriVariables, array $context): CartResource
    {
        $quote = $this->reach->acceptable($customer, $uriVariables);
        $revision = QuoteRequestBody::of($this->requestStack->getCurrentRequest(), ['revision'])->integer('revision');

        $cart = $this->quotes->accept($quote, $customer, (int) $revision);

        // The cart is put in the session only where there is one already: the API does not start one.
        if ($this->requestStack->getMainRequest()?->hasSession(true)) {
            $this->cartSwitcher->putInSession($cart);
        }

        $resource = $this->transformer->modelToResource(
            resourceClass: CartResource::class,
            propelModel: $cart,
            context: $this->withGroupsOf($operation, $context),
        );

        if (!$resource instanceof CartResource) {
            throw new \LogicException('The cart of an accepted quote was not read as a cart resource.');
        }

        return $resource;
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    private function reject(Customer $customer, Operation $operation, array $uriVariables, array $context): QuoteResource
    {
        $quote = $this->reach->answerable($customer, $uriVariables);
        $revision = QuoteRequestBody::of($this->requestStack->getCurrentRequest(), ['revision'])->integer('revision');

        return $this->quoteResource($this->quotes->reject($quote, $customer, (int) $revision), $operation, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function quoteResource(QuoteModel $quote, Operation $operation, array $context): QuoteResource
    {
        $resource = $this->transformer->modelToResource(
            resourceClass: QuoteResource::class,
            propelModel: $quote,
            context: $this->withGroupsOf($operation, $context),
        );

        \assert($resource instanceof QuoteResource);

        return $resource;
    }

    /**
     * What the answer carries is what the operation says it does.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function withGroupsOf(Operation $operation, array $context): array
    {
        $context['groups'] = $operation->getNormalizationContext()['groups'] ?? [];

        return $context;
    }
}
