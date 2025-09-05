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

namespace Thelia\Domain\Checkout\EventBuilder;

use Thelia\Core\Event\Cart\CartCheckoutEvent;
use Thelia\Domain\Cart\DTO\CartDTOInterface;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Shared\Contract\BuilderEventActionInterface;
use Thelia\Domain\Shared\Contract\DTOEventActionInterface;
use Thelia\Domain\Shared\Trait\ValidateDTOEventActionTrait;

class CartCheckoutEventBuilder implements BuilderEventActionInterface
{
    use ValidateDTOEventActionTrait;

    public function buildEvent(DTOEventActionInterface|CartDTOInterface $data): CartCheckoutEvent
    {
        $this->validateClass($data);

        return (new CartCheckoutEvent($data->getCart()))->bindArray($data->toArray());
    }

    public function getSupportedDTOClasses(): array
    {
        return [
            CheckoutDTO::class,
        ];
    }
}
