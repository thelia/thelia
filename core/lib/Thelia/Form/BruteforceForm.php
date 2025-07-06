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

use Thelia\Model\ConfigQuery;

/**
 * Class BruteforceForm.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class BruteforceForm extends FirewallForm
{
    public const DEFAULT_TIME_TO_WAIT = 10; // 10 minutes

    public const DEFAULT_ATTEMPTS = 10;

    public function getConfigTime()
    {
        return ConfigQuery::read('form_firewall_bruteforce_time_to_wait', static::DEFAULT_TIME_TO_WAIT);
    }

    public function getConfigAttempts()
    {
        return ConfigQuery::read('form_firewall_bruteforce_attempts', static::DEFAULT_ATTEMPTS);
    }
}
