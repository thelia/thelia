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
 * Class GenerateRewrittenUrlEvent
 * @package Thelia\Core\Event
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class GenerateRewrittenUrlEvent extends ActionEvent
{
    protected $object;
    protected $locale;

    protected $url;

    public function __construct($object, $locale)
    {
        $this->object;
        $this->locale;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function isRewritten()
    {
        return null !== $this->url;
    }

    public function getUrl()
    {
        return $this->url;
    }
}
