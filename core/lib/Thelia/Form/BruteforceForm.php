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

use Thelia\Model\ConfigQuery;

/**
 * Class BruteforceForm
 * @package Thelia\Form
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class BruteforceForm extends FirewallForm
{
    const DEFAULT_TIME_TO_WAIT = 10; // 10 minutes

    const DEFAULT_ATTEMPTS = 10;

    public function getConfigTime()
    {
        return ConfigQuery::read("form_firewall_bruteforce_time_to_wait", static::DEFAULT_TIME_TO_WAIT);
    }

    public function getConfigAttempts()
    {
        return ConfigQuery::read("form_firewall_bruteforce_attempts", static::DEFAULT_ATTEMPTS);
    }
}
