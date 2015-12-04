<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Event;

use Symfony\Component\EventDispatcher\Event;
use Thelia\Core\Serializer\SerializerInterface;

/**
 * Class ExportEvent
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ExportEvent extends Event
{
    protected $handler;

    /**
     * @var \Thelia\Core\Serializer\SerializerInterface
     */
    protected $serializer;

    /**
     * Event constructor
     *
     * @param SerializerInterface $serializer A serializer instance
     */
    public function __construct($handler, SerializerInterface $serializer)
    {
        $this->handler = $handler;
        $this->serializer = $serializer;
    }

    /**
     * Get serializer
     *
     * @return SerializerInterface
     */
    public function getSerializer()
    {
        return $this->serializer;
    }

    /**
     * Set serializer
     *
     * @param SerializerInterface $serializer A serializer instance
     *
     * @return $this Return $this, allow chaining
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;

        return $this;
    }
}
