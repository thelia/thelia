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

namespace Thelia\Core\Event\File;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Event\ToggleVisibilityEvent;

/**
 * Class FileToggleVisibilityEvent.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class FileToggleVisibilityEvent extends ToggleVisibilityEvent
{
    protected $query;

    public function __construct(ModelCriteria $query, $object_id)
    {
        parent::__construct($object_id);

        $this->setQuery($query);
    }

    public function setQuery(ModelCriteria $query): void
    {
        $this->query = $query;
    }

    public function getQuery(): ?ModelCriteria
    {
        return $this->query;
    }
}
