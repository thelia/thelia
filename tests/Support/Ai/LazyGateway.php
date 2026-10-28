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

namespace Thelia\Tests\Support\Ai;

use Thelia\Domain\Ai\AiGatewayInterface;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\AiResponse;

/**
 * A gateway that is given its target after construction: the topic guardrail needs the gateway that runs it.
 */
final class LazyGateway implements AiGatewayInterface
{
    public ?AiGatewayInterface $inner = null;

    public function complete(AiRequest $request): AiResponse
    {
        return $this->inner()->complete($request);
    }

    public function stream(AiRequest $request): iterable
    {
        return $this->inner()->stream($request);
    }

    private function inner(): AiGatewayInterface
    {
        return $this->inner ?? throw new \LogicException('The gateway was not given yet.');
    }
}
