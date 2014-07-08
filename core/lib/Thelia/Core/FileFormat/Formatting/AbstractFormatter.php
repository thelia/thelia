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
<<<<<<< HEAD
=======
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
>>>>>>> Remove conflicts

/**
 * Class AbstractFormatter
 * @package Thelia\Core\FileFormat\Formatting
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class AbstractFormatter implements FormatInterface, FormatterInterface
{
<<<<<<< HEAD

=======
    /** @var \Thelia\Core\Translation\Translator  */
    protected $translator;

    /** @var \Thelia\Log\Tlog */
    protected $logger;

    /** @var array  */
    protected $aliases = array();

    public function __construct()
    {
        $this->translator = Translator::getInstance();

        $this->logger = Tlog::getInstance();
    }

    public function setAliases(array $aliases)
    {
        $this->aliases = $aliases;

        return $this;
    }

    public function getAliases()
    {
        return $this->aliases;
    }
>>>>>>> Remove conflicts
}
