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

namespace Thelia\Core\Event\Message;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Message;

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\MessageEvent
 */
class MessageEvent extends ActionEvent
{
    protected $message;

    public function __construct(Message $message = null)
    {
        $this->message = $message;
    }

    public function hasMessage()
    {
        return ! \is_null($this->message);
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }
}
