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
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\Cart;
use Thelia\Tools\TokenProvider;

/**
 * The cart token is a bearer secret: an anonymous cart is restored from a cookie
 * that carries it. POST /front/carts is anonymous, so the body must never fix the
 * token — that would let an attacker plant a known one and hijack the cart. The
 * server assigns an unguessable token here instead.
 */
final readonly class CartCreationProcessor implements ProcessorInterface
{
    public function __construct(
        private PropelPersistProcessor $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Cart && empty($data->token)) {
            $data->token = TokenProvider::generateToken();
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
