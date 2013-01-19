<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Tests\Log;

use Thelia\Log\Tlog;
use Thelia\Core\Thelia;

class TlogTest extends \PHPUnit_Framework_TestCase
{
    protected static $logger;

    protected $regex = "/(\\d)(.)(\\s+)((?:[a-z][a-z]+))(\\s+)(\\[.*?\\])(\\s+)(\\{.*?\\})(\\s+)((?:2|1)\\d{3}(?:-|\\/)(?:(?:0[1-9])|(?:1[0-2]))(?:-|\\/)(?:(?:0[1-9])|(?:[1-2][0-9])|(?:3[0-1]))(?:T|\\s)(?:(?:[0-1][0-9])|(?:2[0-3])):(?:[0-5][0-9]):(?:[0-5][0-9]))(.)(\\s+)(%s)/is";

    public static function setUpBeforeClass()
    {        
        self::$logger = Tlog::getInstance();
        
        self::$logger->setDestinations("Thelia\Log\Destination\TlogDestinationText");
        self::$logger->setLevel(Tlog::TRACE);
        self::$logger->setFiles("*");
    }

    public function testTraceWithTraceLevel()
    {

        $logger = self::$logger;
        $logger->setLevel(Tlog::TRACE);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, "foo"));
        $logger->trace("foo");
    }

    public function testTraceWitoutTraceLevel()
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::MUET);

        $this->expectOutputRegex("/^$/");
        $logger->trace("foo");
    }

    public function testDebugWithDebugLevel()
    {

        $logger = self::$logger;
        $logger->setLevel(Tlog::DEBUG);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, "foo"));
        $logger->debug("foo");
    }

    public function testDebugWitoutDebugLevel()
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::MUET);

        $this->expectOutputRegex("/^$/");
        $logger->debug("foo");
    }

    public function testInfoWithInfoLevel()
    {

        $logger = self::$logger;
        $logger->setLevel(Tlog::INFO);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, "foo"));
        $logger->info("foo");
    }

    public function testInfoWitoutInfoLevel()
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::MUET);

        $this->expectOutputRegex("/^$/");
        $logger->info("foo");
    }

    public function testWarningWithWarningLevel()
    {

        $logger = self::$logger;
        $logger->setLevel(Tlog::WARNING);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, "foo"));
        $logger->warning("foo");
    }

    public function testWarningWitoutWarningLevel()
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::MUET);

        $this->expectOutputRegex("/^$/");
        $logger->warning("foo");
    }

    public function testErrorWithErrorLevel()
    {

        $logger = self::$logger;
        $logger->setLevel(Tlog::ERROR);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, "foo"));
        $logger->error("foo");
    }

    public function testErrorWitoutErrorLevel()
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::MUET);

        $this->expectOutputRegex("/^$/");
        $logger->error("foo");
    }

    public function testFatalWithFatalLevel()
    {

        $logger = self::$logger;
        $logger->setLevel(Tlog::FATAL);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, "foo"));
        $logger->fatal("foo");
    }

    public function testFatalWitoutFatalLevel()
    {
        $logger = self::$logger;
        $logger->setLevel(Tlog::MUET);

        $this->expectOutputRegex("/^$/");
        $logger->fatal("foo");
    }
}
