<?php

namespace Thelia\Model;

use Thelia\Model\Base\LangQuery as BaseLangQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'lang' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class LangQuery extends BaseLangQuery
{
    public function findByIdOrLocale($search)
    {
        $find = $this->findPk($search);

        if (null === $find) {
            $find = $this->findOneByLocale($search);
        }

        return $find;
    }
}
