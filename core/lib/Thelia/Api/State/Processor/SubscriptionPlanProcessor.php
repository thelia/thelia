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
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\SubscriptionPlan;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\SubscriptionPlanPriceQuery;

/**
 * Saves a plan, then its prices, in one transaction: a price refused by the database leaves
 * the plan as it was. A list of prices that is sent replaces the prices of the plan, one entry
 * per currency; a write that leaves `prices` out leaves them alone. A plan with no price in
 * the default currency could not be sold in it, and is refused when the list does not
 * give one.
 */
final readonly class SubscriptionPlanProcessor implements ProcessorInterface
{
    public function __construct(
        private PropelPersistProcessor $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof SubscriptionPlan) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $prices = $data->getPrices();
        $connection = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        $connection->beginTransaction();

        try {
            $saved = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

            if (null !== $prices && $saved instanceof SubscriptionPlan && null !== $saved->getId()) {
                $this->replacePrices((int) $saved->getId(), $prices);
                $saved->setPrices($prices);
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        return $saved;
    }

    /**
     * @param list<array<string, mixed>> $prices
     */
    private function replacePrices(int $planId, array $prices): void
    {
        $kept = [];

        foreach ($prices as $entry) {
            $currencyId = (int) ($entry['currencyId'] ?? 0);

            if (null === CurrencyQuery::create()->findPk($currencyId)) {
                throw new UnprocessableEntityHttpException(\sprintf('Unknown currency %d.', $currencyId));
            }

            if (!isset($entry['price']) || !is_numeric($entry['price']) || (float) $entry['price'] < 0) {
                throw new UnprocessableEntityHttpException('A price is a positive number.');
            }

            $row = SubscriptionPlanPriceQuery::create()
                ->filterBySubscriptionPlanId($planId)
                ->filterByCurrencyId($currencyId)
                ->findOneOrCreate();
            $row
                ->setPrice(number_format(round((float) $entry['price'], 6), 6, '.', ''))
                ->setFromDefaultCurrency((int) (bool) ($entry['fromDefaultCurrency'] ?? false))
                ->save();
            $kept[] = $currencyId;
        }

        SubscriptionPlanPriceQuery::create()
            ->filterBySubscriptionPlanId($planId)
            ->filterByCurrencyId($kept, Criteria::NOT_IN)
            ->delete();

        $defaultCurrencyId = (int) Currency::getDefaultCurrency()->getId();

        if ([] !== $prices && !\in_array($defaultCurrencyId, $kept, true)) {
            throw new UnprocessableEntityHttpException('A plan needs a price in the default currency.');
        }
    }
}
