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
 * Class ApiSendJsonTest
 * @package Thelia\Tests\Api
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ApiSendJsonTest extends ApiTestCase
{
    public function testSendNotValidJson()
    {
        $data = "this is not a valid json";

        $client = static::createClient();
        $servers = $this->getServerParameters();
        $servers['CONTENT_TYPE'] = 'application/json';
        $client->request(
            'POST',
            '/api/products?sign='.$this->getSignParameter($data),
            [],
            [],
            $servers,
            $data
        );

        $this->assertEquals(400, $client->getResponse()->getStatusCode());
    }
}
