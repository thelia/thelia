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

namespace Thelia\Core\Event\Lang;

/**
 * Class LangDeleteEvent
 * @package Thelia\Core\Event\Lang
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class LangDeleteEvent extends LangEvent
{
    /**
     * @var int
     */
    protected $lang_id;

    /**
     * @param int $lang_id
     */
    public function __construct($lang_id)
    {
        $this->lang_id = $lang_id;
    }

    /**
     * @param int $lang_id
     *
     * @return $this
     */
    public function setLangId($lang_id)
    {
        $this->lang_id = $lang_id;

        return $this;
    }

    /**
     * @return int
     */
    public function getLangId()
    {
        return $this->lang_id;
    }
}
