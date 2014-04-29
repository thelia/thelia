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

namespace Thelia\Model\Tools;

/**
 * Class SitemapURL
 * @package Thelia\Model\Tools
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class SitemapURL
{
    /**
     * URL of the page.
     *
     * @var string
     */
    protected $loc = null;

    /**
     * The date of last modification of the file.
     *
     * @var string
     */
    protected $lastmod = null;

    /**
     * How frequently the page is likely to change.
     *
     * @var string
     */
    protected $changfreq = null;

    /**
     * The priority of this URL relative to other URLs on your site. Valid values range from 0.0 to 1.0.
     *
     * @var float
     */
    protected $priotity = null;

    public function __construct($loc, $lastmod=null)
    {
        $this->loc = $loc;
        $this->lastmod = $lastmod;
    }

    /**
     * @param string $changfreq
     */
    public function setChangfreq($changfreq)
    {
        $this->changfreq = $changfreq;
    }

    /**
     * @return string
     */
    public function getChangfreq()
    {
        return $this->changfreq;
    }

    /**
     * @param string $lastmod
     */
    public function setLastmod($lastmod)
    {
        $this->lastmod = $lastmod;
    }

    /**
     * @return string
     */
    public function getLastmod()
    {
        return $this->lastmod;
    }

    /**
     * @param string $loc
     */
    public function setLoc($loc)
    {
        $this->loc = $loc;
    }

    /**
     * @return string
     */
    public function getLoc()
    {
        return $this->loc;
    }

    /**
     * @param float $priotity
     */
    public function setPriotity($priotity)
    {
        $this->priotity = $priotity;
    }

    /**
     * @return float
     */
    public function getPriotity()
    {
        return $this->priotity;
    }

}
