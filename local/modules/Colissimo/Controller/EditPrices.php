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

namespace Colissimo\Controller;

use Colissimo\Colissimo;
use Colissimo\Model\Config\ColissimoConfigValue;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Model\AreaQuery;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Tools\URL;

/**
 * Class EditPrices
 * @package Colissimo\Controller
 * @author Thelia <info@thelia.net>
 */
class EditPrices extends BaseAdminController
{
    public function editprices()
    {
        // Get data & treat
        $post = $this->getRequest();
        $operation = $post->get('operation');
        $area = $post->get('area');
        $weight = $post->get('weight');
        $price = $post->get('price');

        if (preg_match("#^add|delete$#", $operation) &&
            preg_match("#^\d+$#", $area) &&
            preg_match("#^\d+\.?\d*$#", $weight)
        ) {
            // check if area exists in db
            $exists = AreaQuery::create()
                ->findPK($area);
            if ($exists !== null) {

                if (null !== $data = Colissimo::getConfigValue(ColissimoConfigValue::PRICES, null)) {
                    $json_data = json_decode(
                        $data,
                        true
                    );
                }
                if ((float) $weight > 0 && $operation == "add"
                  && preg_match("#\d+\.?\d*#", $price)) {
                    $json_data[$area]['slices'][$weight] = $price;
                } elseif ($operation == "delete") {
                    if (isset($json_data[$area]['slices'][$weight])) {
                        unset($json_data[$area]['slices'][$weight]);
                    }
                } else {
                    throw new \Exception("Weight must be superior to 0");
                }
                ksort($json_data[$area]['slices']);

                Colissimo::setConfigValue(ColissimoConfigValue::PRICES, json_encode($json_data));

            } else {
                throw new \Exception("Area not found");
            }
        } else {
            throw new \ErrorException("Arguments are missing or invalid");
        }

        return $this->redirectToConfigurationPage();
    }

    /**
     * Redirect to the configuration page
     */
    protected function redirectToConfigurationPage()
    {
        return RedirectResponse::create(URL::getInstance()->absoluteUrl('/admin/module/Colissimo'));
    }
}
