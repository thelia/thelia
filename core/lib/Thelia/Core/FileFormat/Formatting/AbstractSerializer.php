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

namespace Thelia\Core\FileFormat\Formatting;

use Thelia\Core\FileFormat\FormatInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;

/**
 * Class AbstractSerializer
 * @package Thelia\Core\FileFormat\Formatting
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class AbstractSerializer implements FormatInterface, SerializerInterface
{
    const FILENAME = "data";

    /** @var \Thelia\Core\Translation\Translator  */
    protected $translator;

    /** @var \Thelia\Log\Tlog */
    protected $logger;

    /** @var array */
    protected $order = array();

    public function __construct()
    {
        $this->translator = Translator::getInstance();

        $this->logger = Tlog::getInstance();
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function setOrder(array $order)
    {
        $this->order = $order;

        return $this;
    }

    public function checkOrders(array $values)
    {
        foreach ($this->getOrder() as $order) {
            if (!array_key_exists($order, $values)) {
                throw new \ErrorException(
                    $this->translator->trans(
                        "The column %column that you want to sort doesn't exist",
                        [
                            "%column" => $order
                        ]
                    )
                );
            }
        }
    }
}
