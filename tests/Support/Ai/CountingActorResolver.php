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

use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiActorResolverInterface;
use Thelia\Domain\Ai\AiAudience;

/**
 * Resolves the actor the test chose and counts how many times it was asked.
 */
final class CountingActorResolver implements AiActorResolverInterface
{
    public int $calls = 0;

    /** @var list<AiAudience|null> */
    public array $audiences = [];

    public function __construct(private readonly AiActor $actor)
    {
    }

    public function resolve(?AiAudience $audience = null): AiActor
    {
        ++$this->calls;
        $this->audiences[] = $audience;

        return $this->actor;
    }
}
