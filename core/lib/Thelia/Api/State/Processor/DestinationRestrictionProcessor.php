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
use Thelia\Api\Resource\DestinationRestriction;
use Thelia\Api\Resource\DestinationRestrictionI18n;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionException;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionFacade;

final readonly class DestinationRestrictionProcessor implements ProcessorInterface
{
    public function __construct(private DestinationRestrictionFacade $facade)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof DestinationRestriction) {
            return $data;
        }

        try {
            if ($operation instanceof DeleteOperationInterface) {
                $this->facade->delete((int) $data->getId());

                return null;
            }

            $wording = [];

            foreach ($data->getI18ns() as $locale => $i18n) {
                if ($i18n instanceof DestinationRestrictionI18n) {
                    $wording[(string) $locale] = ['title' => $i18n->getTitle(), 'reason' => $i18n->getReason()];
                }
            }

            $group = $this->facade->save($data->getId(), (string) $data->getCode(), $data->getMode(), $data->isActive(), $wording);
        } catch (DestinationRestrictionException $refusal) {
            throw new UnprocessableEntityHttpException($refusal->getMessage(), $refusal);
        }

        $data->setId($group->getId());
        $data->setPosition($group->getPosition());
        $data->setCreatedAt($group->getCreatedAt());
        $data->setUpdatedAt($group->getUpdatedAt());
        $data->setPropelModel($group);

        return $data;
    }
}
