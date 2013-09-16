<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Model\Tools;

use Thelia\Tools\URL;
/**
 * A trait for managing Rewriten URLs from model classes
 */
trait UrlRewritingTrait {

    /**
     * @returns string the view name of the rewriten object (e.g., 'category', 'product')
     */
    protected abstract function getRewritenUrlViewName();

    /**
     * Get the object URL for the given locale, rewriten if rewriting is enabled.
     *
     * @param string $locale a valid locale (e.g. en_US)
     */
    public function getUrl($locale)
    {
        return URL::getInstance()->retrieve($this->getRewritenUrlViewName(), $this->getId(), $locale)->toString();
    }

    /**
     * Generate a rewriten URL from the object title, and store it in the rewriting table
     *
     * @param string $locale a valid locale (e.g. en_US)
     */
    public function generateRewritenUrl($locale)
    {
        URL::getInstance()->generateRewritenUrl($this->getRewritenUrlViewName(), $this->getId(), $locale, $this->getTitle());
    }

    /**
     * return the rewriten URL for the given locale
     *
     * @param string $locale a valid locale (e.g. en_US)
     */
    public function getRewritenUrl($locale)
    {
        return "fake url - TODO";
    }

    /**
     * Set the rewriten URL for the given locale
     *
     * @param string $locale a valid locale (e.g. en_US)
     */
    public function setRewritenUrl($locale, $url)
    {
        // TODO - code me !

        return $this;
    }
}