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


/**
 * Class ApiTestCase
 * @package Thelia\Tests
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ApiTestCase extends WebTestCase
{

    protected function getServerParameters()
    {
        return [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_AUTHORIZATION' => 'Token 79E95BD784CADA0C9A578282E'
        ];
    }


}