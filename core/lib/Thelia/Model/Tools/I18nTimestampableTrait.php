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

namespace Thelia\Model\Tools;

use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Trait I18nTimestampableTrait.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
trait I18nTimestampableTrait
{
    public function postSave(ConnectionInterface $con = null)
    {
        $this->getBaseQueryObject()
            ->filterById($this->getId())
            ->update([$this->getUpdatedAtColumnName() => new \DateTime()], $con)
        ;

        parent::postSave($con);
    }

    /**
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria
     */
    protected function getBaseQueryObject()
    {
        $parentClass = preg_replace("#^([\w\_\\\\]+)I18n$#", '$1Query', __CLASS__);

        return new $parentClass();
    }

    protected function getUpdatedAtColumnName()
    {
        return 'UpdatedAt';
    }
}
