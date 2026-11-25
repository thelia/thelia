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

namespace Thelia\Api\Bridge\Propel\Filter;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\Clock\Clock;
use Thelia\Domain\Quote\Enum\QuoteState;

/**
 * Filters the quotes by the state they are read in.
 *
 * A quote that waits for the buyer past its last valid day is expired whether or not the console
 * has written it yet (see QuoteTransitions::effectiveState()), and the payload says expired: the
 * filter answers the same, so that "state=expired" lists what reads expired and "state=quoted"
 * only what can still be accepted.
 */
final class QuoteStateFilter extends AbstractFilter
{
    private const string PREFIX = 'quote_state_';

    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if ('state' !== $property || null === $value || !$this->isPropertyEnabled($property, $resourceClass)) {
            return;
        }

        $states = [];

        foreach ((array) $value as $key => $candidate) {
            if (\is_int($key) && \is_string($candidate) && null !== QuoteState::tryFrom($candidate)) {
                $states[$candidate] = QuoteState::from($candidate);
            }
        }

        if ([] === $states) {
            // Nothing the filter knows: no quote is in a state that does not exist.
            $query->add($query->getTableMap()->getName().'.id', null, Criteria::ISNULL);

            return;
        }

        $table = $query->getTableMap()->getName();
        $stateColumn = $table.'.state';
        $validUntilColumn = $table.'.valid_until';
        $today = Clock::get()->now()->format('Y-m-d');
        $names = [];

        foreach ($states as $state) {
            $name = self::PREFIX.$state->value;

            match ($state) {
                QuoteState::Expired => $this->expired($query, $name, $stateColumn, $validUntilColumn, $today),
                QuoteState::Quoted => $this->stillOpen($query, $name, $stateColumn, $validUntilColumn, $today),
                default => $query->addCond($name, $stateColumn, $state->value, Criteria::EQUAL),
            };

            $names[] = $name;
        }

        $query->where($names, Criteria::LOGICAL_OR);
    }

    private function expired(ModelCriteria $query, string $name, string $stateColumn, string $validUntilColumn, string $today): void
    {
        $query->addCond($name.'_written', $stateColumn, QuoteState::Expired->value, Criteria::EQUAL);
        $query->addCond($name.'_waiting', $stateColumn, QuoteState::Quoted->value, Criteria::EQUAL);
        $query->addCond($name.'_past', $validUntilColumn, $today, Criteria::LESS_THAN);
        $query->combine([$name.'_waiting', $name.'_past'], Criteria::LOGICAL_AND, $name.'_overdue');
        $query->combine([$name.'_written', $name.'_overdue'], Criteria::LOGICAL_OR, $name);
    }

    private function stillOpen(ModelCriteria $query, string $name, string $stateColumn, string $validUntilColumn, string $today): void
    {
        $query->addCond($name.'_waiting', $stateColumn, QuoteState::Quoted->value, Criteria::EQUAL);
        $query->addCond($name.'_unbounded', $validUntilColumn, null, Criteria::ISNULL);
        $query->addCond($name.'_future', $validUntilColumn, $today, Criteria::GREATER_EQUAL);
        $query->combine([$name.'_unbounded', $name.'_future'], Criteria::LOGICAL_OR, $name.'_valid');
        $query->combine([$name.'_waiting', $name.'_valid'], Criteria::LOGICAL_AND, $name);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'state' => [
                'property' => 'state',
                'type' => 'string',
                'required' => false,
                'schema' => ['type' => 'string', 'enum' => array_map(static fn (QuoteState $state): string => $state->value, QuoteState::cases())],
            ],
            'state[]' => [
                'property' => 'state',
                'type' => 'string',
                'required' => false,
                'is_collection' => true,
                'schema' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => array_map(static fn (QuoteState $state): string => $state->value, QuoteState::cases())]],
            ],
        ];
    }
}
