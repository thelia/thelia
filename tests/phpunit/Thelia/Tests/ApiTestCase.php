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

namespace Thelia\Tests;

use Thelia\Model\ApiQuery;

/**
 * Class ApiTestCase
 * @package Thelia\Tests
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ApiTestCase extends WebTestCase
{
    const API_KEY = "79E95BD784CADA0C9A578282E";

    protected function getServerParameters()
    {
        return [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_AUTHORIZATION' => 'Token '.self::API_KEY
        ];
    }

    protected function getSignParameter($content)
    {
        $api = ApiQuery::create()
            ->findOneByApiKey(self::API_KEY);

        $secureKey = pack('H*', $api->getSecureKey());

        return hash_hmac('sha1', $content, $secureKey);
    }
}
