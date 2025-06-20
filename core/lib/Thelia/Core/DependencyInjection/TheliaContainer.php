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
namespace Thelia\Core\DependencyInjection;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * To override the methods of the symfony container.
 *
 * Class TheliaContainer
 *
 * @author Gilles Bourgeat <manu@raynaud.io>
 *
 * @since 2.3
 */
class TheliaContainer extends Container
{
    public function set(string $id, ?object $service): void
    {
        if ($id === 'request' && \PHP_SAPI === 'cli' && !isset($this->services['request_stack'])
        ) {
            $this->services['request_stack'] = new RequestStack();
        }

        parent::set($id, $service);
    }
}
