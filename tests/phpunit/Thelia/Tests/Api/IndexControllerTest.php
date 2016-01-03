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

namespace Thelia\Tests\Api;

use Thelia\Tests\ApiTestCase;

/**
 * Class IndexControllerTest
 * @package Thelia\Tests\Api
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class IndexControllerTest extends ApiTestCase
{
    public function testIndexAction()
    {
        $client = static::createClient();

        $crawler = $client->request(
            'GET',
            '/api'
        );
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }
}
