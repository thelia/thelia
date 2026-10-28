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

namespace Thelia\Domain\Ai\Guardrail;

/**
 * Implemented by the guardrails that depend on a connection, so that the back office can tell the merchant
 * "this one cannot run: connect Mistral" without calling anything. A guardrail that does not implement it
 * is taken as usable.
 *
 * @internal
 */
interface AiGuardrailAvailabilityInterface
{
    public function availability(): GuardrailAvailability;
}
