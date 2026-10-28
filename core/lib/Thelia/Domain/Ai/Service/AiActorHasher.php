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

namespace Thelia\Domain\Ai\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Domain\Ai\AiActor;

/**
 * The actor of a call as the log keeps it: an HMAC-SHA256 of `<type>:<identifier>` with the secret of the
 * application. It counts an actor and tells two of them apart; it cannot be reversed to a person.
 *
 * @internal
 */
final readonly class AiActorHasher
{
    public function __construct(
        #[\SensitiveParameter]
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    public function hash(AiActor $actor): string
    {
        return hash_hmac('sha256', $actor->type->value.':'.$actor->identifier, $this->secret);
    }
}
