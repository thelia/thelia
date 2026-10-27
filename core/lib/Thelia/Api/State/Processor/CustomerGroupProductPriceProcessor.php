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
use Symfony\Component\HttpFoundation\Response;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\CustomerGroupProductPrice;
use Thelia\Model\CustomerGroupProductPriceQuery;

/**
 * A group price emptied of its last amount (no price, no promo price) says nothing: the model
 * drops the row, and there is nothing left to answer with. The write that empties it is a
 * deletion, answered as one (204), rather than a representation of a row that is gone.
 * A creation with no amount is refused by the resource before it gets here.
 */
final readonly class CustomerGroupProductPriceProcessor implements ProcessorInterface
{
    public function __construct(
        private PropelPersistProcessor $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof CustomerGroupProductPrice && isset($uriVariables['id']) && null === $data->price && null === $data->promoPrice) {
            CustomerGroupProductPriceQuery::create()->findPk($uriVariables['id'])?->delete();

            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
