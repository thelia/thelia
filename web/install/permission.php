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

?>
<?php
$step = 2;
include("header.php");

try {
    $checkPermission = new \Thelia\Install\CheckPermission(true, $trans);
    $isValid = $checkPermission->exec();
    $validationMessage = $checkPermission->getValidationMessages();
    $_SESSION['install']['return_step'] = 'permission.php';
    $_SESSION['install']['continue'] = $isValid;
    $_SESSION['install']['current_step'] = 'permission.php';
    $_SESSION['install']['step'] = 2;
    ?>
    <div class="well">

        <p class="lead"><?php echo $trans->trans('Checking PHP version and permissions'); ?></p>
        <ul class="list-unstyled list-group">
            <?php foreach ($validationMessage as $item => $data): ?>
                <li class="list-group-item <?php if ($data['status']) {
                    echo 'text-success';
                } else {
                    echo 'text-danger';
                } ?>">
                    <?php echo $data['text']; ?>
                    <?php if (!$data['status']) {
                        echo $data['hint'];
                    } ?>
                </li>
            <?php endforeach; ?>
        </ul>

    </div>
    <div class="clearfix">
        <?php if ($isValid) { ?>
            <a href="connection.php" class="pull-right btn btn-default btn-primary"><span class="glyphicon glyphicon-chevron-right"></span>
                <?php echo $trans->trans('Continue'); ?>
            </a>
        <?php } else { ?>
            <a href="permission.php" class="pull-right btn btn-default btn-danger"><span class="glyphicon glyphicon-refresh"></span>
                <?php echo $trans->trans('Refresh'); ?>
            </a>
        <?php } ?>
    </div>
<?php
}
catch (\Thelia\Install\Exception\AlreadyInstallException $ex) {
        ?>
        <div class="alert alert-danger">
            <?php echo $trans->trans(
                'It seems that Thelia is already installed on this system. Please check configuration, perform some cleanup if required, an try again.'
            ); ?>
        </div>
        <?php
}
catch (\Exception $ex) {
    ?>
    <div class="alert alert-danger">
        <?php echo $trans->trans('<p><strong>Sorry, an unexpected error occured</strong>: %err</p><p>Error details:</p><p>%details</p>', [
                '%err' => $ex->getMessage(),
                '%details' => nl2br($ex->getTraceAsString())
            ]); ?>
    </div>
    <?php
}

include("footer.php");
?>
