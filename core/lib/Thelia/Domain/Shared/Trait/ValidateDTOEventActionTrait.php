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

namespace Thelia\Domain\Shared\Trait;

use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Domain\Shared\Contract\BuilderEventActionInterface;
use Thelia\Domain\Shared\Contract\DTOEventActionInterface;

/**
 * @mixin BuilderEventActionInterface
 */
trait ValidateDTOEventActionTrait
{
    /**
     * @throws \InvalidArgumentException
     */
    public function validateClass(DTOEventActionInterface $data): void
    {
        if (\in_array($data::class, $this->getSupportedDTOClasses(), true)) {
            return;
        }
        throw new \InvalidArgumentException(\sprintf('Bad data type for building %s: %s', CartEvent::class, $data::class));
    }
}
