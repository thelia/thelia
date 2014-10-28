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

/**
 * Class TCacheEvent
 * @package Thelia\Core\Event
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class TCacheEvent extends ActionEvent
{
    protected $response = null;

    /**
     * @param null $response
     */
    public function setResponse($response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * @return null
     */
    public function getResponse()
    {
        return $this->response;
    }
}
