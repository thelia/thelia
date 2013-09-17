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

$step = 3;
include("header.php");
if(!$_SESSION['install']['continue'] && $_SESSION['install']['step'] == 2) {
    header(sprintf('location: %s', $_SESSION['install']['return_step']));
}

$_SESSION['install']['step'] = 3;
?>

    <form action="bdd.php" method="POST" >
        <?php if(isset($_GET['err']) && $_GET['err'] == 1){ ?>
            <div class="alert alert-danger">Wrong connection information</div>
        <?php } ?>
        <div class="well">
            <div class="form-group">
                <label for="host">Host :</label>
                <input id="host" class="form-control" type="text" name="host" placeholder="localhost" value="<?php if(isset($_SESSION['install']['host'])){ echo $_SESSION['install']['host']; } ?>">
            </div>

            <div class="form-group">
                <label for="user">Username :</label>
                <input id="user" type="text" class="form-control" name="username" placeholder="john" value="<?php if(isset($_SESSION['install']['username'])){ echo $_SESSION['install']['username']; } ?>">
            </div>

            <div class="form-group">
                <label for="password">Password :</label>
                <input id="password" type="password" class="form-control" name="password" placeholder="l33t 5p34k" >
            </div>
            <div class="form-group{if $error} has-error{/if}">
                <label for="port">Port :</label>
                <input id="port" type="text" class="form-control" name="port" value="<?php if(isset($_SESSION['install']['port'])){ echo $_SESSION['install']['port']; } else { echo '3306'; } ?>">
            </div>

        </div>

        <div class="clearfix">
            <div class="control-btn">
                <button type="submit" class="pull-right btn btn-default btn-primary">Continue</button>
            </div>

        </div>
    </form>

<?php include("footer.php"); ?>