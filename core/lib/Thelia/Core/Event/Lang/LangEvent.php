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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Lang;

/**
 * Class LangEvent
 * @package Thelia\Core\Event\Lang
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class LangEvent extends ActionEvent
{
    /**
     * @var \Thelia\Model\Lang
     */
    protected $lang;

    public function __construct(Lang $lang = null)
    {
        $this->lang = $lang;
    }

    /**
     * @param \Thelia\Model\Lang $lang
     */
    public function setLang(Lang $lang)
    {
        $this->lang = $lang;
    }

    /**
     * @return \Thelia\Model\Lang
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     *
     * check if lang object is present
     *
     * @return bool
     */
    public function hasLang()
    {
        return null !== $this->lang;
    }
}
