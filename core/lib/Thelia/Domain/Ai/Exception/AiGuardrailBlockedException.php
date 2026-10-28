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

namespace Thelia\Domain\Ai\Exception;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;

/**
 * A guardrail in block mode refused the request (input stage) or the answer (output stage). The verdict
 * says which one and why; the reason is meant for the log, not for a visitor. $logId is the row of the log
 * when the call was already paid and logged (an output blocked after the answer), null before the call.
 */
#[Exclude]
final class AiGuardrailBlockedException extends \RuntimeException implements AiExceptionInterface
{
    public function __construct(public readonly GuardrailVerdict $verdict, public readonly ?int $logId = null)
    {
        parent::__construct(\sprintf('The AI guardrail "%s" blocked the %s.', $verdict->guardrail, $verdict->stage->value));
    }
}
