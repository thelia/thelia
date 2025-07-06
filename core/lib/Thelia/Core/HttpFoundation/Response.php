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
namespace Thelia\Core\HttpFoundation;

use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Thelia\Log\Tlog;

/**
 * extends Thelia\Core\HttpFoundation\Response for adding some helpers.
 *
 * Class Response
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class Response extends BaseResponse
{
    public function sendContent(): static
    {
        // ConfigQuery can be not already generated in cache so we must check it
        if (class_exists('ConfigQuery')) {
            Tlog::getInstance()->write($this->content);
        }

        return parent::sendContent();
    }
}
