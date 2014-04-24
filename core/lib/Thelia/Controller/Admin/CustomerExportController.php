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

namespace Thelia\Controller\Admin;

use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\CustomerQuery;
use Thelia\Model\NewsletterQuery;

/**
 * Class CustomerExportController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CustomerExportController extends BaseAdminController
{

    public function NewsletterExportAction()
    {
        if (null !== $response = $this->checkAuth([AdminResources::EXPORT_CUSTOMER_NEWSLETTER], [], [AccessManager::VIEW])) {
            return $response;
        }

        $data = NewsletterQuery::create()
            ->select([
                    'email',
                    'firstname',
                    'lastname',
                    'locale'
                ])
            ->find();

        $handle = fopen('php://memory', 'r+');

        fputcsv($handle, ['email','firstname','lastname','locale'], ';', '"');

        foreach ($data->toArray() as $customer) {
            fputcsv($handle, $customer, ';', '"');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return Response::create(
            $content,
            200,
            array(
                "Content-Type"=>"application/csv-tab-delimited-table",
                "Content-disposition"=>"filename=export_customer_newsletter.csv"
            )
        );

    }

}