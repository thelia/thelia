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
        <p>Checking permissions</p>
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
            <a href="connection.php" class="pull-right btn btn-default btn-primary"><span class="glyphicon glyphicon-chevron-right"></span> Continue</a>
        <?php } else { ?>
            <a href="permission.php" class="pull-right btn btn-default btn-danger"><span class="glyphicon glyphicon-refresh"></span> refresh</a>
        <?php } ?>
    </div>
<?php include("footer.php"); ?>