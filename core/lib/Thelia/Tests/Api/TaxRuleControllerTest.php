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
 * Class TaxRuleControllerTest
 * @package Thelia\Tests\Api
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class TaxRuleControllerTest extends ApiTestCase
{

    public function testListAction()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/tax-rules?sign='.$this->getSignParameter(''),
            [],
            [],
            $this->getServerParameters()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');
        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertGreaterThan(0, count($content), 'The request must return at least one result');
    }
}
