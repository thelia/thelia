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

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Resource\DestinationRestrictionCountry;
use Thelia\Api\Resource\ProductDestinationRestriction;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionException;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionFacade;
use Thelia\Model\DestinationRestrictionCountry as DestinationRestrictionCountryModel;
use Thelia\Model\ProductDestinationRestriction as ProductDestinationRestrictionModel;

/**
 * The destinations of a group and the products in it, written through the facade so
 * that a region outside its country or an unknown product is refused the way the back
 * office refuses it.
 */
final readonly class DestinationRestrictionLinkProcessor implements ProcessorInterface
{
    public function __construct(private DestinationRestrictionFacade $facade)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof DestinationRestrictionCountry && !$data instanceof ProductDestinationRestriction) {
            return $data;
        }

        try {
            if ($operation instanceof DeleteOperationInterface) {
                $this->remove($data);

                return null;
            }

            $written = $data instanceof DestinationRestrictionCountry
                ? $this->facade->addDestination((int) $data->getDestinationRestriction()->getId(), (int) $data->getCountry()->getId(), $data->getState()?->getId())
                : $this->facade->attachProduct((int) $data->getDestinationRestriction()->getId(), (int) $data->getProduct()->getId());
        } catch (DestinationRestrictionException $refusal) {
            throw new UnprocessableEntityHttpException($refusal->getMessage(), $refusal);
        }

        $data->setId($written->getId());
        $data->setPropelModel($written);

        return $data;
    }

    private function remove(DestinationRestrictionCountry|ProductDestinationRestriction $data): void
    {
        $link = $data->getPropelModel();

        if ($link instanceof DestinationRestrictionCountryModel) {
            $this->facade->removeDestination((int) $link->getDestinationRestrictionId(), (int) $link->getId());

            return;
        }

        if ($link instanceof ProductDestinationRestrictionModel) {
            $this->facade->detachProduct((int) $link->getDestinationRestrictionId(), (int) $link->getProductId());

            return;
        }

        throw new UnprocessableEntityHttpException('This link cannot be read back to be removed.');
    }
}
