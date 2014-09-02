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
global $thelia;

$checkPermission = new \Thelia\Install\CheckPermission(true, $thelia->getContainer()->get('thelia.translator'));
$isValid = $checkPermission->exec();
$validationMessage = $checkPermission->getValidationMessages();
$_SESSION['install']['return_step'] = 'permission.php';
$_SESSION['install']['continue'] = $isValid;
$_SESSION['install']['current_step'] = 'permission.php';
$_SESSION['install']['step'] = 2;
?>
    <div class="well">
        <p><?php echo $trans->trans('Checking permissions'); ?></p>
        <ul class="list-unstyled list-group">
            <?php foreach($validationMessage as $item => $data): ?>
            <li class="list-group-item <?php if ($data['status']) {echo 'text-success';} else { echo 'text-danger';} ?>">
                <?php echo $data['text']; ?>
                <?php if (!$data['status']) { echo $data['hint']; } ?>
            </li>
            <?php endforeach; ?>
        </ul>

    </div>
    <div class="clearfix">
        <?php if($isValid){ ?>
            <a href="connection.php" class="pull-right btn btn-default btn-primary"><span class="glyphicon glyphicon-chevron-right"></span> <?php echo $trans->trans('Continue'); ?></a>
        <?php } else { ?>
            <a href="permission.php" class="pull-right btn btn-default btn-danger"><span class="glyphicon glyphicon-refresh"></span> refresh</a>
        <?php } ?>
    </div>
<?php include("footer.php"); ?>