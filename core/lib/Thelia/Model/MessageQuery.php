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

namespace Thelia\Model;

use Thelia\Model\Base\MessageQuery as BaseMessageQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'message' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class MessageQuery extends BaseMessageQuery
{
    /**
     * Load a message from its name, throwing an excemtoipn is none is found.
     *
     * @param  string     $messageName the message name
     * @return Message    the loaded message
     * @throws \Exception if the message could not be loaded
     */
    public static function getFromName($messageName)
    {
        if (false === $message = MessageQuery::create()->filterByName($messageName)->findOne()) {
            throw new \Exception("Failed to load message $messageName.");
        }

        return $message;
    }
}
// MessageQuery
