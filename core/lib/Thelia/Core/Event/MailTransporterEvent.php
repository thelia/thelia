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

namespace Thelia\Core\Event;

/**
 * Class MailTransporterEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class MailTransporterEvent extends ActionEvent
{
    /**
     * @var \Swift_Transport
     */
    protected $transporter;

    public function setMailerTransporter(\Swift_Transport $transporter): void
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
