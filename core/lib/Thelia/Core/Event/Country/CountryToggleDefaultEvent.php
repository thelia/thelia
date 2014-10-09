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

namespace Thelia\Core\Event\Country;

/**
 * Class CountryToggleDefaultEvent
 * @package Thelia\Core\Event\Country
 * @author manuel raynaud <manu@thelia.net>
 */
class CountryToggleDefaultEvent extends CountryEvent
{
    protected $country_id;

    public function __construct($country_id)
    {
        $this->country_id = $country_id;
    }

    /**
     * @return mixed
     */
    public function getCountryId()
    {
        return $this->country_id;
    }
}
