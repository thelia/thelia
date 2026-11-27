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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\Quote as QuoteResource;
use Thelia\Api\Service\Quote\AdminQuoteLookup;
use Thelia\Api\Service\Quote\QuoteRequestBody;
use Thelia\Domain\Quote\DTO\QuoteDraftInput;
use Thelia\Domain\Quote\DTO\QuoteDraftLine;
use Thelia\Domain\Quote\Exception\QuoteDraftRefusedException;
use Thelia\Domain\Quote\QuoteFacade;
use Thelia\Model\Admin;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Quote;

/**
 * What the administration does with a quote: starts one for a customer, writes the draft of the next
 * revision, sends it, pushes the validity back, reminds the buyer, deletes one that was never sent.
 *
 * A state is never written here: each operation hands the work to the quote facade, which moves the
 * quote through the transitions the shop allows and refuses the others. This reads the body field by
 * field and answers the quote with its revisions, the draft included. The facade's refusals reach
 * the error mapping of the API (api_platform.php) unchanged.
 *
 * @implements ProcessorInterface<mixed, QuoteResource|null>
 */
final readonly class QuoteAdminProcessor implements ProcessorInterface
{
    private const int MAXIMUM_DRAFT_LINES = 500;

    public function __construct(
        private QuoteFacade $quotes,
        private AdminQuoteLookup $lookup,
        private ApiResourcePropelTransformerService $transformer,
        private RequestStack $requestStack,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?QuoteResource
    {
        $admin = $this->security->getUser();
        $admin = $admin instanceof Admin ? $admin : null;

        if (QuoteResource::OPERATION_ADMIN_DELETE === $operation->getName()) {
            $this->quotes->delete($this->quoteOf($uriVariables));

            return null;
        }

        $quote = match ($operation->getName()) {
            QuoteResource::OPERATION_ADMIN_CREATE => $this->create($admin),
            QuoteResource::OPERATION_ADMIN_DRAFT => $this->draft($admin, $this->quoteOf($uriVariables)),
            QuoteResource::OPERATION_ADMIN_SEND => $this->quotes->send($this->quoteOf($uriVariables), $admin),
            QuoteResource::OPERATION_ADMIN_EXTEND => $this->extend($this->quoteOf($uriVariables)),
            QuoteResource::OPERATION_ADMIN_REMIND => $this->quotes->remind($this->quoteOf($uriVariables)),
            default => throw new \LogicException(\sprintf('The quote processor of the administration does not handle the operation "%s".', (string) $operation->getName())),
        };

        $context['groups'] = $operation->getNormalizationContext()['groups'] ?? [];
        $resource = $this->transformer->modelToResource(resourceClass: QuoteResource::class, propelModel: $quote, context: $context);

        \assert($resource instanceof QuoteResource);

        return $resource;
    }

    private function create(?Admin $admin): Quote
    {
        $customerId = QuoteRequestBody::of($this->requestStack->getCurrentRequest(), ['customerId'])->integer('customerId');
        $customer = null === $customerId || $customerId < 1 ? null : CustomerQuery::create()->findPk($customerId);

        return $this->quotes->createForCustomer($customer ?? throw QuoteDraftRefusedException::customerCannotHaveQuotes(), $admin);
    }

    private function draft(?Admin $admin, Quote $quote): Quote
    {
        $body = QuoteRequestBody::of($this->requestStack->getCurrentRequest(), ['lines', 'discount', 'postage', 'deliveryModuleId', 'merchantNote', 'validUntil']);
        $lines = [];

        foreach ($body->objects('lines', ['quoteItemId', 'productSaleElementsId', 'quantity', 'price'], self::MAXIMUM_DRAFT_LINES) as $line) {
            $lines[] = new QuoteDraftLine(
                $line->integer('quoteItemId', false),
                (int) $line->integer('productSaleElementsId'),
                (float) $line->number('quantity'),
                (string) $line->amount('price'),
            );
        }

        $this->quotes->saveDraft($quote, $admin, new QuoteDraftInput(
            $lines,
            $body->amount('discount', false) ?? '0',
            $body->amount('postage', false) ?? '0',
            $body->integer('deliveryModuleId', false),
            $body->text('merchantNote'),
            $body->day('validUntil'),
        ));

        return $quote;
    }

    private function extend(Quote $quote): Quote
    {
        $day = QuoteRequestBody::of($this->requestStack->getCurrentRequest(), ['validUntil'])->day('validUntil');

        return $this->quotes->extend($quote, $day ?? throw new UnprocessableEntityHttpException('"validUntil" is required.'));
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function quoteOf(array $uriVariables): Quote
    {
        return $this->lookup->fromUriVariables($uriVariables);
    }
}
