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

namespace Thelia\ImportExport\Export\Type;

use Thelia\ImportExport\Export\AbstractExport;
use Thelia\Model\NewsletterQuery;

/**
 * Class MailingExport
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class MailingExport extends AbstractExport
{
    const FILE_NAME = 'mailing';

    protected $orderAndAliases = [
        'Id' => 'Identifiant',
        'Email' => 'Email',
        'Fistname' => 'Prénom',
        'Lastname' => 'Nom'
    ];

    protected function getData()
    {
        return new NewsletterQuery;
    }
}
