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

namespace Thelia\Domain\Form\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Thelia\Form\BruteforceForm;
use Thelia\Form\FirewallForm;
use Thelia\Model\ConfigQuery;
use Thelia\Model\FormFirewallQuery;

/**
 * The form firewall records the IP address of every form submission it watches.
 * A record is only read back while it sits inside the waiting window, so anything
 * older is an IP address kept for nothing.
 */
class FormFirewallPurger
{
    /**
     * @throws PropelException
     */
    public function purgeExpiredEntries(int $days): int
    {
        return FormFirewallQuery::create()
            ->filterByUpdatedAt($this->getThresholdDate($days), Criteria::LESS_THAN)
            ->delete();
    }

    /**
     * Never delete a record the firewall would still consult, whatever the
     * configured retention is: purging one early would hand a blocked IP
     * address a fresh set of attempts.
     */
    private function getThresholdDate(int $days): \DateTime
    {
        $threshold = (new \DateTime())->modify(\sprintf('-%d days', $days));
        $waitingWindowStart = (new \DateTime())->modify(\sprintf('-%d minutes', $this->getLongestWaitingTime()));

        return min($threshold, $waitingWindowStart);
    }

    private function getLongestWaitingTime(): int
    {
        return max(
            (int) ConfigQuery::read('form_firewall_time_to_wait', FirewallForm::DEFAULT_TIME_TO_WAIT),
            (int) ConfigQuery::read('form_firewall_bruteforce_time_to_wait', BruteforceForm::DEFAULT_TIME_TO_WAIT),
        );
    }
}
