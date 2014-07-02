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

namespace Thelia\Controller\Admin;

use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\NewsletterQuery;

/**
 * Class CustomerExportController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CustomerExportController extends BaseAdminController
{

    public function newsletterExportAction()
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
