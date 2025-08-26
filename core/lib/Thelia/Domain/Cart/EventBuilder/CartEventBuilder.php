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

namespace Thelia\Domain\Cart\EventBuilder;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Domain\Cart\DTO\CartDTO;
use Thelia\Domain\Cart\DTO\CartDTOInterface;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Cart\DTO\CartItemDeleteDTO;
use Thelia\Domain\Cart\DTO\CartItemUpdateQuantityDTO;
use Thelia\Domain\Cart\Service\CartRetriever;
use Thelia\Domain\Shared\Contract\BuilderEventActionInterface;
use Thelia\Domain\Shared\Contract\DTOEventActionInterface;
use Thelia\Domain\Shared\Trait\ValidateDTOEventActionTrait;

class CartEventBuilder implements BuilderEventActionInterface
{
    use ValidateDTOEventActionTrait;

    public function __construct(
        protected RequestStack $requestStack,
        protected EventDispatcherInterface $eventDispatcher,
        protected CartRetriever $cartRetriever,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function buildEvent(DTOEventActionInterface|CartDTOInterface $data): CartEvent
    {
        $this->validateClass($data);

        return (new CartEvent($data->getCart()))->bindArray($data->toArray());
    }

    public function getSupportedDTOClasses(): array
    {
        return [
            CartItemAddDTO::class,
            CartItemDeleteDTO::class,
            CartItemUpdateQuantityDTO::class,
            CartDTO::class,
        ];
    }
}
