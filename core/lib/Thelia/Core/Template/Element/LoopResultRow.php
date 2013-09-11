<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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

namespace Thelia\Core\Template\Element;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;

class LoopResultRow
{
    protected $substitution = array();

    public $model = null;
    public $loopResult;

    public $versionable = false;
    public $timestampable = false;
    public $countable = false;

    public function __construct($loopResult = null, $model = null, $versionable = false, $timestampable = false, $countable = true)
    {
        if ($model instanceof ActiveRecordInterface) {
            $this->model = $model;

            $this->versionable = $versionable;
            $this->timestampable = $timestampable;
        }

        if ($loopResult instanceof LoopResult) {
            $this->loopResult = $loopResult;

            $this->countable = $countable;
        }

        $this->assignDefaultOutputs();
    }

    public function set($key, $value)
    {
        $this->substitution[$key] = $value === null ? '' : $value;

        return $this;
    }

    public function get($key)
    {
        return $this->substitution[$key];
    }

    public function getVarVal()
    {
        return $this->substitution;
    }

    public function getVars()
    {
        return array_keys($this->substitution);
    }

    protected function getTimestampOutputs()
    {
        return array(
            array('CREATE_DATE', 'getCreatedAt'),
            array('UPDATE_DATE', 'getUpdatedAt'),
        );
    }

    protected function getVersionOutputs()
    {
        return array(
            array('VERSION', 'getVersion'),
            array('VERSION_DATE', 'getVersionCreatedAt'),
            array('VERSION_AUTHOR', 'getVersionCreatedBy'),
        );
    }

    protected function assignDefaultOutputs()
    {
        if (true === $this->versionable) {
            foreach ($this->getVersionOutputs() as $output) {
                $this->set($output[0], $this->model->$output[1]());
            }
        }
        if (true === $this->timestampable) {
            foreach ($this->getTimestampOutputs() as $output) {
                $this->set($output[0], $this->model->$output[1]());
            }
        }
        if (true === $this->countable) {
            $this->set('LOOP_COUNT', 1 + $this->loopResult->getCount());
            $this->set('LOOP_TOTAL', $this->loopResult->modelCollection->count());
        }
    }
}
