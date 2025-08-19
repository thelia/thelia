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

namespace Thelia\Core\Controller;

use Symfony\Component\HttpKernel\Controller\ContainerControllerResolver;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @deprecated since Thelia 2.5, use Symfony\Component\HttpKernel\Controller\ContainerControllerResolver instead
 */
class ControllerResolver extends ContainerControllerResolver
{
    /* nothing here */
}
