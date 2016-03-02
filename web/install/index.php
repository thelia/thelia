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

include("php_version_check.php");

$step = 1;
include("header.php");

?>
					<div class="well">
						<div class="clearfix text-center">
							<a href="?lang=fr_FR">
								Français
							</a>
							<span>&nbsp;<strong>|</strong>&nbsp;</span>
							<a href="?lang=en_US">
								English
							</a>
							<span>&nbsp;<strong>|</strong>&nbsp;</span>
							<a href="?lang=de_DE">
								Deutsch
							</a>
						</div>
						<br />

						<p class="lead text-center">
<?php echo $trans->trans('Welcome in the Thelia installation wizard.'); ?>
						</p>
						<p class="text-center">
<?php echo $trans->trans('We will guide you throughout this process to install any application on your system.'); ?>
						</p>
					</div>
					<div class="clearfix">
						<a href="permission.php" class="pull-right btn btn-default btn-primary"><span class="glyphicon glyphicon-chevron-right"></span> <?php echo $trans->trans('Continue'); ?></a>
					</div>
<?php include("footer.php"); ?>