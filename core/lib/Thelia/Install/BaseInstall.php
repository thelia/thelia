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

namespace Thelia\Install;

use Thelia\Core\Thelia;
use Thelia\Install\Exception\AlreadyInstallException;

/**
 * Class BaseInstall.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
abstract class BaseInstall
{
    /** @var bool If Installation wizard is launched by CLI */
    protected bool $isConsoleMode;

    /**
     * Constructor.
     *
     * @param bool $verifyInstall Verify if an installation already exists
     *
     * @throws AlreadyInstallException
     */
    public function __construct($verifyInstall = true)
    {
        // Check if install wizard is launched via CLI
        $this->isConsoleMode = \PHP_SAPI === 'cli';

        if (Thelia::isInstalled() && $verifyInstall) {
            throw new AlreadyInstallException('Thelia is already installed');
        }

        $this->exec();
    }

    abstract public function exec();
}
