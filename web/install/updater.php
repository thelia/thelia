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

use Thelia\Install\Exception\UpdateException;

$context = 'update';
$step = 2;

include("header.php");

// todo: check security

// Retrieve the website url
$url = $_SERVER['PHP_SELF'];
$website_url = preg_replace("#/install/[a-z](.*)#" ,'', $url);

?>
    <div class="well">

        <p class="lead text-center">
            <?php echo $trans->trans('Updating Thelia.'); ?>
        </p>

        <?php
        $update = new \Thelia\Install\Update(false);
        if ($update->isLatestVersion()) { ?>

            <div class="alert alert-warning">
                <p><?php
                    echo $trans->trans('It seems that Thelia database is already up to date.');
                    ?></p>
            </div>

        <?php } else {

            $updateError = null;

            try {
                $update->process();
            } catch (UpdateException $ex) {
                $updateError = $ex;
            }

            if (null === $updateError) { ?>

                <div class="alert alert-success">
                    <p><?php
                        echo $trans->trans(
                            'Thelia as been successfully updated to version %version',
                            ['%version' => $update->getCurrentVersion()]
                        );
                    ?></p>
                </div>

                <p class="lead text-center">
                    <a href="<?php echo $website_url; ?>/index.php/admin" id="admin_url"><?php echo $trans->trans('Go to back office'); ?></a>
                </p>

            <?php } else { ?>
                <div class="alert alert-danger">
                <?php echo $trans->trans(
                    '<p><strong>Sorry, an unexpected error occured</strong>: %err</p><p>Error details:</p><p>%details</p>',
                    [
                        '%err' => $updateError->getMessage(),
                        '%details' => nl2br($updateError->getTraceAsString())
                    ]
                ); ?>
                </div>
            <?php } ?>

            <p class="lead"><?php echo $trans->trans('Update proccess trace'); ?></p>
            <ul class="list-unstyled list-group">
                <?php foreach ($update->getUpdatedVersions() as $version) { ?>
                    <li class="list-group-item text-success"><?php
                        echo $trans->trans("update to version %version", ['%version' => $version]);
                    ?></li>
                <?php }

                if (null !== $updateError) { ?>
                    <li class="list-group-item text-danger"><?php
                        echo $trans->trans("update to version %version", ['%version' => $updateError->getVersion()]);
                    ?></li>
                <?php } ?>
            </ul>

        <?php } ?>

    </div>
<?php

include("footer.php");

