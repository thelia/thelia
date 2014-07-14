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
use Symfony\Component\HttpFoundation\Request;
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
     * Those values are for a "normal" security context
     */
    const DEFAULT_TIME_TO_WAIT = 1;
    const DEFAULT_ATTEMPTS = 3;

    /** @var  \Thelia\Model\FormFirewall */
    protected static $cachedInstance;

    public function __construct(Request $request, $type = "form", $data = array(), $options = array())
    {
        parent::__construct($request, $type, $data, $options);

        static::$cachedInstance = FormFirewallQuery::create()
            ->filterByFormName($this->getName())
            ->filterByIpAddress($this->request->getClientIp())
            ->findOne()
        ;
    }

    public function isFirewallOk()
    {
        if (null !== $firewallRow = &static::$cachedInstance) {
            /** @var \DateTime $lastRequestDateTime */
            $lastRequestDateTime = $firewallRow->getUpdatedAt();

            $lastRequestTimestamp = $lastRequestDateTime->getTimestamp();

            /**
             * Get the last request execution time in hour.
             */
            $lastRequest = (time() - $lastRequestTimestamp) / 3600;

            if ($lastRequest > $this->getConfigTime()) {
                $firewallRow->resetAttempts();
            }

            if ($firewallRow->getAttempts() < $this->getConfigAttempts()) {
                $firewallRow->incrementAttempts();
            } else {
                /** Set updated_at at NOW() */
                $firewallRow->save();

                return false;
            }
        } else {
            $firewallRow = (new FormFirewall())
                ->setIpAddress($this->request->getClientIp())
                ->setFormName($this->getName())
            ;
            $firewallRow->save();

            static::$cachedInstance = $firewallRow;
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
}
