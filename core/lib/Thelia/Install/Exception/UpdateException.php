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

namespace Thelia\Install\Exception;

/**
 * Class UpdateException.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class UpdateException extends \RuntimeException
{
    /** @var string the version that has failed */
    protected string $version;

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }
}
