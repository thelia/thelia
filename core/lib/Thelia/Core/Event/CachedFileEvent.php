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

namespace Thelia\Core\Event;

class CachedFileEvent extends ActionEvent
{
    /**
     * @var string The complete file name (with path) of the source file
     */
    protected $source_filepath = null;
    /**
     * @var string The target subdirectory in the cache
     */
    protected $cache_subdirectory = null;

    /**
     * @var string The absolute URL of the cached file (in the web space)
     */
    protected $file_url = null;

    /**
     * @var string The absolute path of the cached file
     */
    protected $cache_filepath = null;

    public function getFileUrl()
    {
        return $this->file_url;
    }

    public function setFileUrl($file_url)
    {
        $this->file_url = $file_url;

        return $this;
    }

    public function getCacheFilepath()
    {
        return $this->cache_filepath;
    }

    public function setCacheFilepath($cache_filepath)
    {
        $this->cache_filepath = $cache_filepath;

        return $this;
    }

    public function getSourceFilepath()
    {
        return $this->source_filepath;
    }

    public function setSourceFilepath($source_filepath)
    {
        $this->source_filepath = $source_filepath;

        return $this;
    }

    public function getCacheSubdirectory()
    {
        return $this->cache_subdirectory;
    }

    public function setCacheSubdirectory($cache_subdirectory)
    {
        $this->cache_subdirectory = $cache_subdirectory;

        return $this;
    }
}
