<?php

namespace Thelia\Tests\Core\HttpFoundation;

use Thelia\Core\HttpFoundation\Request;


class RequestTest extends \PHPUnit_Framework_TestCase
{

    public function testGetUriAddingParameters()
    {
        $request = $this->getMock(
            "Thelia\Core\HttpFoundation\Request",
            array("getUri", "getQueryString")
        );

        $request->expects($this->any())
                ->method("getUri")
                ->will($this->onConsecutiveCalls(
                "http://localhost/",
                "http://localhost/?test=fu"
            ));

        $request->expects($this->any())
                ->method("getQueryString")
                ->will($this->onConsecutiveCalls(
                "",
                "test=fu"
            ));

        $result = $request->getUriAddingParameters(array("foo" => "bar"));

        $this->assertEquals("http://localhost/?foo=bar", $result);

        $result = $request->getUriAddingParameters(array("foo" => "bar"));

        $this->assertEquals("http://localhost/?test=fu&foo=bar", $result);


    }

}