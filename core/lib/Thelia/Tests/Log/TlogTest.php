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
    protected $logger;

    protected $regex = "/(\\d)(.)(\\s+)((?:[a-z][a-z]+))(\\s+)(\\[.*?\\])(\\s+)(\\{.*?\\})(\\s+)((?:2|1)\\d{3}(?:-|\\/)(?:(?:0[1-9])|(?:1[0-2]))(?:-|\\/)(?:(?:0[1-9])|(?:[1-2][0-9])|(?:3[0-1]))(?:T|\\s)(?:(?:[0-1][0-9])|(?:2[0-3])):(?:[0-5][0-9]):(?:[0-5][0-9]))(.)(\\s+)(%s)/is";

    public function setUp()
    {
        parent::setUp();

        $_SERVER['REMOTE_ADDR'] = '::1';

        $containerMock = $this->getMock("Symfony\Component\DependencyInjection\Container", array("get", "getParameter"));

        $configModel = new ConfigModel();

        $containerMock->expects($this->any())
                ->method("get")
                ->will($this->returnValue($configModel));
        $containerMock->expects($this->any())
                ->method("getParameter")
                ->with($this->stringContains("logger.class"))
                ->will($this->returnValue("Thelia\Log\Tlog"));

        $this->logger = new Tlog($containerMock);

        $this->logger->set_destinations("Thelia\Log\Destination\TlogDestinationText");
        $this->logger->set_level(Tlog::TRACE);
        $this->logger->set_files("*");
    }

    public function testTraceWithTraceLevel()
    {

        $logger = $this->logger;
        $logger->set_level(Tlog::TRACE);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, "foo"));
        $logger->trace("foo");
    }

    public function testTraceWitoutTraceLevel()
    {
        $logger = $this->logger;
        $logger->set_level(Tlog::MUET);

        $this->expectOutputRegex("/^$/");
        $logger->trace("foo");
    }

    public function testDebugWithDebugLevel()
    {

        $logger = $this->logger;
        $logger->set_level(Tlog::DEBUG);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, "foo"));
        $logger->debug("foo");
    }

    public function testDebugWitoutDebugLevel()
    {
        $logger = $this->logger;
        $logger->set_level(Tlog::MUET);

        $this->expectOutputRegex("/^$/");
        $logger->debug("foo");
    }

    public function testInfoWithInfoLevel()
    {

        $logger = $this->logger;
        $logger->set_level(Tlog::INFO);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, "foo"));
        $logger->info("foo");
    }

    public function testInfoWitoutInfoLevel()
    {
        $logger = $this->logger;
        $logger->set_level(Tlog::MUET);

        $this->expectOutputRegex("/^$/");
        $logger->info("foo");
    }

    public function testWarningWithWarningLevel()
    {

        $logger = $this->logger;
        $logger->set_level(Tlog::WARNING);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, "foo"));
        $logger->warning("foo");
    }

    public function testWarningWitoutWarningLevel()
    {
        $logger = $this->logger;
        $logger->set_level(Tlog::MUET);

        $this->expectOutputRegex("/^$/");
        $logger->warning("foo");
    }

    public function testErrorWithErrorLevel()
    {

        $logger = $this->logger;
        $logger->set_level(Tlog::ERROR);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, "foo"));
        $logger->error("foo");
    }

    public function testErrorWitoutErrorLevel()
    {
        $logger = $this->logger;
        $logger->set_level(Tlog::MUET);

        $this->expectOutputRegex("/^$/");
        $logger->error("foo");
    }

    public function testFatalWithFatalLevel()
    {

        $logger = $this->logger;
        $logger->set_level(Tlog::FATAL);

        //"#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: "
        $this->expectOutputRegex(sprintf($this->regex, "foo"));
        $logger->fatal("foo");
    }

    public function testFatalWitoutFatalLevel()
    {
        $logger = $this->logger;
        $logger->set_level(Tlog::MUET);

        $this->expectOutputRegex("/^$/");
        $logger->fatal("foo");
    }
}

class ConfigModel
{
    public function __call($name, $arguments)
    {
        return $arguments[1];
    }
}
