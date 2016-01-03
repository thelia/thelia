<?php

namespace Carousel\Model;

use Carousel\Model\Base\CarouselQuery as BaseCarouselQuery;


/**
 * Skeleton subclass for performing query and update operations on the 'carousel' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class CarouselQuery extends BaseCarouselQuery
{
    public function findAllByPosition()
    {
        return $this->orderByPosition()
            ->find();
    }
} // CarouselQuery
