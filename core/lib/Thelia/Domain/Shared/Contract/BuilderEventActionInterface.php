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

namespace Thelia\Domain\Shared\Contract;

use Thelia\Core\Event\ActionEvent;

interface BuilderEventActionInterface
{
    public function buildEvent(DTOEventActionInterface $data): ActionEvent;

    public function getSupportedDTOClasses(): array;
}
