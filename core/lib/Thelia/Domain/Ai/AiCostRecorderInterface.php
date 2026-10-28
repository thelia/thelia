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

namespace Thelia\Domain\Ai;

/**
 * The way a module accounts for a service it pays outside the gateway (a scoring API, a search API): ask
 * before the call, write the cost after. The cost counts in the monthly budget of the usage and in the
 * global cap from the moment it is written; recording it never fails because of them.
 */
interface AiCostRecorderInterface
{
    /**
     * Checks, before the call, what the gateway checks before its own: the usage is active, the service is
     * allowed by the optional setting "EU only" (off by default), the budget of the usage and the global cap are not reached.
     *
     * @param string $provider    the service about to be paid, as AiExternalCost::$provider: `typesafe`
     * @param string $maximumCost the highest cost the call can reach, a positive decimal with a point; the budget check
     *                            counts it as spent, so a call that would cross the budget is refused before it runs.
     *                            '0' only checks that the budget is not reached yet
     *
     * @throws Exception\UnknownAiUsageException
     * @throws Exception\InactiveAiUsageException
     * @throws Exception\AiJurisdictionRefusedException when the merchant turned "EU only" on and the service is outside the EU
     * @throws Exception\AiBudgetExceededException
     */
    public function authorize(string $usageId, string $provider, AiJurisdiction $jurisdiction, string $maximumCost = '0'): void;

    /**
     * @return int the identifier of the row written to the usage log
     *
     * @throws Exception\UnknownAiUsageException when no service declares the usage
     */
    public function record(AiExternalCost $cost): int;
}
