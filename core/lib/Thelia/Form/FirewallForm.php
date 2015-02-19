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
namespace Thelia\Form;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\FormFirewall;
use Thelia\Model\FormFirewallQuery;

/**
 * Class FirewallForm
 * @package Thelia\Form
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class FirewallForm extends BaseForm
{
    /**
     * Those values are for a "normal" security policy
     *
     * Time is in minutes
     */
    const DEFAULT_TIME_TO_WAIT = 60; // 1 hour
    const DEFAULT_ATTEMPTS = 6;

    public function isFirewallOk($env)
    {
        if ($env === "prod" && $this->isFirewallActive()) {
            /**
             * Empty the firewall
             */
            $deleteTime = date("Y-m-d G:i:s", time() - $this->getConfigTime() * 60);
            $collection = FormFirewallQuery::create()
                ->filterByFormName($this->getName())
                ->filterByUpdatedAt($deleteTime, Criteria::LESS_THAN)
                ->find();

            $collection->delete();

            $firewallInstance = FormFirewallQuery::create()
                ->filterByFormName($this->getName())
                ->filterByIpAddress($this->request->getClientIp())
                ->findOne()
            ;

            if (null !== $firewallInstance) {
                if ($firewallInstance->getAttempts() < $this->getConfigAttempts()) {
                    $firewallInstance->incrementAttempts();
                } else {
                    /** Set updated_at at NOW() */
                    $firewallInstance->save();

                    return false;
                }
            } else {
                $firewallInstance = (new FormFirewall())
                    ->setIpAddress($this->request->getClientIp())
                    ->setFormName($this->getName())
                ;
                $firewallInstance->save();
            }
        }

        return true;
    }

    /**
     * @return int
     *
     * The time (in hours) to wait if the attempts have been exceeded
     */
    public function getConfigTime()
    {
        return ConfigQuery::read("form_firewall_time_to_wait", static::DEFAULT_TIME_TO_WAIT);
    }

    /**
     * @return int
     *
     * The number of allowed attempts
     */
    public function getConfigAttempts()
    {
        return ConfigQuery::read("form_firewall_attempts", static::DEFAULT_ATTEMPTS);
    }

    public function isFirewallActive()
    {
        return ConfigQuery::read("form_firewall_active", true);
    }

    public function getWaitingTime()
    {
        $translator = Translator::getInstance();
        $minutes = $this->getConfigTime();
        $minutesName = $translator->trans("minute(s)");
        $text = "";

        if ($minutes >= 60) {
            $hour = floor($minutes / 60);
            $minutes %= 60;
            $text = $hour." ".$translator->trans("hour(s)")." ";
        }

        if ($minutes !== 0) {
            $text .= $minutes." ".$minutesName;
        } else {
            $text = rtrim($text);
        }

        return $text;
    }
}
