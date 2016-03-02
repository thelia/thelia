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

$step = 3;
include("header.php");
if(!$_SESSION['install']['continue'] && $_SESSION['install']['step'] == 2) {
    header(sprintf('location: %s', $_SESSION['install']['return_step']));
}

$_SESSION['install']['step'] = 3;
?>

    <form action="bdd.php" method="POST" >
        <?php if(isset($_GET['err']) && $_GET['err'] == 1){ ?>
            <div class="alert alert-danger"><?php echo $trans->trans('Wrong connection information'); ?></div>
        <?php } ?>
        <div class="well">
            <fieldset>
                <legend><?php echo $trans->trans('Database connection configuration'); ?></legend>
                <div class="form-group">
                    <label for="host"><?php echo $trans->trans('Host :'); ?></label>
                    <input id="host" class="form-control" type="text" name="host" placeholder="localhost" value="<?php if(isset($_SESSION['install']['host'])){ echo $_SESSION['install']['host']; } ?>">
                </div>

                <div class="form-group">
                    <label for="user"><?php echo $trans->trans('Username :'); ?></label>
                    <input id="user" type="text" class="form-control" name="username" placeholder="john" value="<?php if(isset($_SESSION['install']['username'])){ echo $_SESSION['install']['username']; } ?>">
                </div>

                <div class="form-group">
                    <label for="password"><?php echo $trans->trans('Password :'); ?></label>
                    <input id="password" type="password" class="form-control" name="password" placeholder="l33t 5p34k" >
                </div>
                <div class="form-group">
                    <label for="port"><?php echo $trans->trans('Port :'); ?></label>
                    <input id="port" type="text" class="form-control" name="port" value="<?php if(isset($_SESSION['install']['port'])){ echo $_SESSION['install']['port']; } else { echo '3306'; } ?>">
                </div>
            </fieldset>
        </div>

        <div class="clearfix">
            <div class="control-btn">
                <button type="submit" class="pull-right btn btn-default btn-primary"><?php echo $trans->trans('Continue'); ?></button>
            </div>

        </div>
    </form>

<?php include("footer.php"); ?>
