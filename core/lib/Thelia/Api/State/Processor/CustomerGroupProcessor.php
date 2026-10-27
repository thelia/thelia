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
use Propel\Runtime\Propel;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\CustomerGroup;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Domain\Customer\Service\CustomerGroupLinks;
use Thelia\Model\CustomerGroupQuery;

/**
 * Saves a customer group, then the categories it may browse and the payment modules it may
 * pay with, in one transaction: a link refused by the database leaves the group as it was.
 * The IRIs were checked by the resource before this point. The links are written by
 * CustomerGroupLinks, which the back office uses too.
 *
 * A list left out of the write is left alone; an empty one clears the links and lifts the
 * restriction, which opens the whole catalog or every payment module to the group. The flags
 * that say a group is restricted are set by CustomerGroupLinks with the lists, never by the
 * resource.
 */
final readonly class CustomerGroupProcessor implements ProcessorInterface
{
    public function __construct(
        private PropelPersistProcessor $persistProcessor,
        private CustomerGroupLinks $groupLinks,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof CustomerGroup) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $connection = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        $connection->beginTransaction();

        try {
            $saved = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

            if ($saved instanceof CustomerGroup && null !== $saved->getId()) {
                $group = CustomerGroupQuery::create()->findPk($saved->getId(), $connection);

                if (null !== $group) {
                    $this->groupLinks->replace($group, $data->getCategoryIds(), $data->getPaymentModuleIds(), $connection);
                }

                $saved->readLinks();
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        return $saved;
    }
}
