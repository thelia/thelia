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

namespace Thelia\Model\Tools;

use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Trait I18nTimestampableTrait
 * @package Thelia\Model\Tools
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
trait I18nTimestampableTrait
{
    public function postSave(ConnectionInterface $con = null)
    {
        parent::postSave($con);

        $this->getBaseQueryObject()
            ->filterById($this->getId())
            ->update([$this->getUpdatedAtColumnName() => new \DateTime()], $con)
        ;
    }

    /**
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria
     */
    protected function getBaseQueryObject()
    {
        $parentClass = preg_replace("#^([\w\_\\\\]+)I18n$#", "$1Query", __CLASS__);

        return (new $parentClass());
    }

    protected function getUpdatedAtColumnName()
    {
        return "UpdatedAt";
    }
}
