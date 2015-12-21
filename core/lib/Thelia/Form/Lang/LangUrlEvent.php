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

namespace Thelia\Form\Lang;

use Thelia\Core\Event\ActionEvent;

/**
 * Class LangUrlEvent
 * @package Thelia\Form\Lang
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class LangUrlEvent extends ActionEvent
{
    protected $url = array();

    public function addUrl($id, $url)
    {
        $this->url[$id] = $url;
    }

    public function getUrl()
    {
        return $this->url;
    }
}
