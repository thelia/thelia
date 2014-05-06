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

use Propel\Runtime\ActiveQuery\ModelCriteria;

class UpdateFilePositionEvent extends UpdatePositionEvent
{
    protected $query;

    /**
     * @param ModelCriteria $query
     * @param               $object_id
     * @param null          $mode
     * @param null          $position
     */
    public function __construct(ModelCriteria $query, $object_id, $mode, $position = null)
    {
        parent::__construct($object_id, $mode, $position);

        $this->setQuery($query);
    }

    /**
     * @param ModelCriteria $query
     */
    public function setQuery(ModelCriteria $query)
    {
        $this->query = $query;
    }

    /**
     * @return ModelCriteria|null
     */
    public function getQuery()
    {
        return $this->query;
    }
}
