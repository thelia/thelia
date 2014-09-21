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

namespace Thelia\Core\Event\Attribute;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Attribute;

class AttributeEvent extends ActionEvent
{
    protected $attribute = null;

    public function __construct(Attribute $attribute = null)
    {
        $this->attribute = $attribute;
    }

    public function hasAttribute()
    {
        return ! is_null($this->attribute);
    }

    public function getAttribute()
    {
        return $this->attribute;
    }

    public function setAttribute($attribute)
    {
        $this->attribute = $attribute;

        return $this;
    }
}
