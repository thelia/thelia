<?php

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
