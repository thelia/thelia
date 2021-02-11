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

namespace Thelia\Core\HttpKernel\Exception;

/**
 * Class RedirectException
 * @package Thelia\Core\HttpKernel\Exception
 * @author manuel raynaud <manu@raynaud.io>
 */
class RedirectException extends \RuntimeException
{
    private $url;
    private $statusCode;

    public function __construct($url, $statusCode = 302, $message = "", $code = 0, \Exception $previous = null)
    {
        $this->url = $url;
        $this->statusCode = $statusCode;

        parent::__construct($message, $code, $previous);
    }

    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }
}
