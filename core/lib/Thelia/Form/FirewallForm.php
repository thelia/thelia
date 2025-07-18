<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Form;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\FormFirewall;
use Thelia\Model\FormFirewallQuery;

/**
 * Class FirewallForm.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class FirewallForm extends BaseForm
{
    /**
     * Those values are for a "normal" security policy.
     *
     * Time is in minutes
     */
    public const DEFAULT_TIME_TO_WAIT = 60;

    // 1 hour
    public const DEFAULT_ATTEMPTS = 6;

    public function isFirewallOk($env)
    {
        if ('prod' === $env && $this->isFirewallActive()) {
            /**
             * Empty the firewall.
             */
            $deleteTime = date('Y-m-d G:i:s', time() - $this->getConfigTime() * 60);
            $collection = FormFirewallQuery::create()
                ->filterByFormName($this::getName())
                ->filterByUpdatedAt($deleteTime, Criteria::LESS_THAN)
                ->find();

            $collection->delete();

            $firewallInstance = FormFirewallQuery::create()
                ->filterByFormName($this::getName())
                ->filterByIpAddress($this->request->getClientIp())
                ->findOne();

            if (null !== $firewallInstance) {
                if ($firewallInstance->getAttempts() < $this->getConfigAttempts()) {
                    $firewallInstance->incrementAttempts();
                } else {
                    /* Set updated_at at NOW() */
                    $firewallInstance->save();

                    return false;
                }
            } else {
                $firewallInstance = (new FormFirewall())
                    ->setIpAddress($this->request->getClientIp())
                    ->setFormName($this::getName());
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
    public function getConfigTime(): int
    {
        return ConfigQuery::read('form_firewall_time_to_wait', static::DEFAULT_TIME_TO_WAIT);
    }

    /**
     * @return int
     *
     * The number of allowed attempts
     */
    public function getConfigAttempts(): int
    {
        return ConfigQuery::read('form_firewall_attempts', static::DEFAULT_ATTEMPTS);
    }

    public function isFirewallActive()
    {
        return ConfigQuery::read('form_firewall_active', true);
    }

    public function getWaitingTime()
    {
        $translator = Translator::getInstance();
        $minutes = $this->getConfigTime();
        $minutesName = $translator->trans('minute(s)');
        $text = '';

        if ($minutes >= 60) {
            $hour = floor($minutes / 60);
            $minutes %= 60;
            $text = $hour.' '.$translator->trans('hour(s)').' ';
        }

        if (0 !== $minutes) {
            $text .= $minutes.' '.$minutesName;
        } else {
            $text = rtrim($text);
        }

        return $text;
    }
}
