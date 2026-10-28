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

namespace Thelia\Tests\Unit\Domain\Ai;

use Thelia\Domain\Ai\AiGatewayInterface;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\AiResponse;
use Thelia\Domain\Ai\Stream\StreamEvent;

/**
 * A gateway that answers what the test scripted, to prove the interface is implementable as documented.
 */
final class ScriptedGateway implements AiGatewayInterface
{
    /** @var list<AiRequest> */
    public array $requests = [];

    /**
     * @param list<AiResponse>  $responses
     * @param list<StreamEvent> $events
     */
    public function __construct(private array $responses, private readonly array $events = [])
    {
    }

    public function complete(AiRequest $request): AiResponse
    {
        $this->requests[] = $request;

        return array_shift($this->responses) ?? throw new \LogicException('No scripted response left.');
    }

    public function stream(AiRequest $request): iterable
    {
        $this->requests[] = $request;

        yield from $this->events;
    }
}
