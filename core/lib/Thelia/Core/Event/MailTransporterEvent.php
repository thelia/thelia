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
 * Class MailTransporterEvent
 * @package Thelia\Core\Event
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class MailTransporterEvent extends ActionEvent
{
    /**
     * @var \Swift_Transport
     */
    protected $transporter;

    public function setMailerTransporter(\Swift_Transport $transporter)
    {
        $this->transporter = $transporter;
    }

    public function getTransporter()
    {
        return $this->transporter;
    }

    public function hasTransporter()
    {
        return null !== $this->transporter;
    }
}
