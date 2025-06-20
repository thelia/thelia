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
namespace Thelia\Core\Event\MetaData;

/**
 * Class MetaDataCreateOrUpdateEvent.
 *
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class MetaDataCreateOrUpdateEvent extends MetaDataDeleteEvent
{
    public function __construct($metaKey = null, $elementKey = null, $elementId = null, protected $value = null)
    {
        parent::__construct($metaKey, $elementKey, $elementId);
    }

    public function setValue($value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }
}
