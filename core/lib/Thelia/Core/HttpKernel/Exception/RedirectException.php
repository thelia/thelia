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
namespace Thelia\Core\HttpKernel\Exception;

use RuntimeException;
use Exception;

/**
 * Class RedirectException.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class RedirectException extends RuntimeException
{
    public function __construct(private $url, private $statusCode = 302, $message = '', $code = 0, Exception $previous = null)
    {
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
