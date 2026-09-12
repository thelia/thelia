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

namespace Thelia\Domain\OrderReturn;

use Thelia\Model\OrderReturnStatus;

/**
 * The authorized transitions of a return between its statuses.
 *
 * The graph is the one described in the return specification:
 *
 *   requested    -> refused | accepted | info_awaited
 *   info_awaited -> requested
 *   accepted     -> received | expired
 *   received     -> settled
 *
 * Comparison is made on the effective (canonical) code, so a custom status
 * declaring an equivalence follows the transitions of the code it stands for.
 */
final class OrderReturnStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        OrderReturnStatus::CODE_REQUESTED => [
            OrderReturnStatus::CODE_REFUSED,
            OrderReturnStatus::CODE_ACCEPTED,
            OrderReturnStatus::CODE_INFO_AWAITED,
        ],
        OrderReturnStatus::CODE_INFO_AWAITED => [
            OrderReturnStatus::CODE_REQUESTED,
        ],
        OrderReturnStatus::CODE_ACCEPTED => [
            OrderReturnStatus::CODE_RECEIVED,
            OrderReturnStatus::CODE_EXPIRED,
        ],
        OrderReturnStatus::CODE_RECEIVED => [
            OrderReturnStatus::CODE_SETTLED,
        ],
        OrderReturnStatus::CODE_REFUSED => [],
        OrderReturnStatus::CODE_SETTLED => [],
        OrderReturnStatus::CODE_EXPIRED => [],
    ];

    /**
     * The codes reachable from the given status code.
     *
     * @return list<string>
     */
    public function allowedTargets(string $fromCode): array
    {
        return self::TRANSITIONS[$fromCode] ?? [];
    }

    /**
     * Whether the transition from one status code to another is authorized.
     */
    public function canTransition(string $fromCode, string $toCode): bool
    {
        return \in_array($toCode, $this->allowedTargets($fromCode), true);
    }

    /**
     * Whether the given code is terminal (no transition leaves it).
     */
    public function isTerminal(string $code): bool
    {
        return [] === $this->allowedTargets($code);
    }
}
