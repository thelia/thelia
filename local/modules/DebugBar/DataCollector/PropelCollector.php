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

namespace DebugBar\DataCollector;
use Propel\Runtime\Propel;
use Psr\Log\LoggerInterface;


/**
 * Class PropelCollector
 * @package DebugBar\DataCollector
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class PropelCollector extends DataCollector implements Renderable, LoggerInterface
{

    protected $statements = array();

    protected $accumulatedTime = 0;

    protected $peakMemory = 0;

    public function __construct()
    {
        $serviceContainer = Propel::getServiceContainer();
        $serviceContainer->setLogger('defaultLogger', $this);

        $con = Propel::getConnection(\Thelia\Model\Map\ProductTableMap::DATABASE_NAME);
        $con->setLogMethods(array(
            'exec',
            'query',
            'execute', // these first three are the default
            'beginTransaction',
            'commit',
            'rollBack',
        ));
    }

    /**
     * Called by the DebugBar when data needs to be collected
     *
     * @return array Collected data
     */
    function collect()
    {
        return array(
            'nb_statements' => count($this->statements),
            'nb_failed_statements' => 0,
            'accumulated_duration' => $this->accumulatedTime,
            'accumulated_duration_str' => $this->formatDuration($this->accumulatedTime),
            'peak_memory_usage' => $this->peakMemory,
            'peak_memory_usage_str' => $this->formatBytes($this->peakMemory),
            'statements' => $this->statements
        );
    }

    /**
     * Returns the unique name of the collector
     *
     * @return string
     */
    public function getName()
    {
        return 'propel';
    }

    /**
     * Returns a hash where keys are control names and their values
     * an array of options as defined in {@see DebugBar\JavascriptRenderer::addControl()}
     *
     * @return array
     */
    public function getWidgets()
    {
        return array(
            "propel" => array(
                "widget" => "PhpDebugBar.Widgets.SQLQueriesWidget",
                "map" => "propel",
                "default" => "[]"
            ),
            "propel:badge" => array(
                "map" => "propel.nb_statements",
                "default" => 0
            )
        );
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @return null
     */
    public function log($level, $message, array $context = array())
    {
        list($sql, $duration_str) = $this->parseAndLogSqlQuery($message);

        $message = "$sql ($duration_str)";
    }

    /**
     * Parse a log line to extract query information
     *
     * @param string $message
     */
    protected function parseAndLogSqlQuery($message)
    {
        $parts = explode('|', $message, 3);
        $duration = 0;
        $memory = 0;
        if (count($parts) > 1) {
            $sql = trim($parts[2]);

            if (preg_match('/([0-9]+\.[0-9]+)/', $parts[0], $matches)) {
                $duration = (float) $matches[1];
            }

            if (preg_match('/([0-9]+\.[0-9]+)([A-Z]{1,2})/', $parts[1], $matches)) {
                $memory = (float) $matches[1];
                if ($matches[2] == 'KB') {
                    $memory *= 1024;
                } else if ($matches[2] == 'MB') {
                    $memory *= 1024 * 1024;
                }
            }
        } else {
            $sql = $parts[0];
        }


        $this->statements[] = array(
            'sql' => $sql,
            'is_success' => true,
            'duration' => $duration,
            'duration_str' => $this->formatDuration($duration),
            'memory' => $memory,
            'memory_str' => $this->formatBytes($memory)
        );
        $this->accumulatedTime += $duration;
        $this->peakMemory = max($this->peakMemory, $memory);
        return array($sql, $this->formatDuration($duration));
    }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function emergency($message, array $context = array())
    {
        $this->log(null, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function alert($message, array $context = array())
    {
        $this->log(null, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function critical($message, array $context = array())
    {
        $this->log(null, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function error($message, array $context = array())
    {
        $this->log(null, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function warning($message, array $context = array())
    {
        $this->log(null, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function notice($message, array $context = array())
    {
        $this->log(null, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function info($message, array $context = array())
    {
        $this->log(null, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function debug($message, array $context = array())
    {
        $this->log(null, $message, $context);
    }


}