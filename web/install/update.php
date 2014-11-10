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

$context = 'update';
$step = 1;

include("header.php");

// todo: check security

?>
<div class="well">
    <div class="clearfix">
        <a href="?lang=fr_FR"><span class="glyphicon glyphicon-chevron-right"></span> <?php echo $trans->trans('French'); ?></a>
        <a href="?lang=en_US"><span class="glyphicon glyphicon-chevron-right"></span> <?php echo $trans->trans('English'); ?></a>
    </div>

    <p class="lead text-center">
        <?php echo $trans->trans('Welcome in the Thelia updater wizard.'); ?>
    </p>

    <?php
    $update = new \Thelia\Install\Update(false);
    if ($update->isLatestVersion()) { ?>

        <div class="alert alert-warning">
            <p><?php
                echo $trans->trans('It seems that Thelia database is already up to date.');
            ?></p>
            <p><em><?php
                echo $trans->trans('For the moment, the wizard allows only an update of the database. To update your php files you must proceed manually.');
            ?></em></p>
        </div>

    <?php } else { ?>

        <div class="alert alert-info">
            <p><?php
            echo $trans->trans(
                'Would you like to update your installation of Thelia from version <strong>%current</strong> to version <strong>%latest</strong>.',
                [
                    '%current' => $update->getCurrentVersion(),
                    '%latest'  => $update->getLatestVersion()
                ]
            );
            ?></p>
        </div>

        <div class="alert alert-warning">
            <p><?php
                echo $trans->trans('It\'s strongly recommended to make a backup before proceeding.');
            ?></p>
        </div>

    <?php } ?>

</div>
<?php if (!$update->isLatestVersion()) { ?>
    <div class="clearfix">
        <a href="updater.php" class="pull-right btn btn-default btn-primary"><span
                class="glyphicon glyphicon-chevron-right"></span> <?php echo $trans->trans('Update Thelia'); ?></a>
    </div>
<?php
}

include("footer.php");

