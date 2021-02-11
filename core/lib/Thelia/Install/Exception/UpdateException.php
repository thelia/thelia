<?php

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
 * Class UpdateException
 * @package Thelia\Install\Exception
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class UpdateException extends \RuntimeException
{
    /** @var string the version that has failed  */
    protected $version;

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param string $version
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }
}
