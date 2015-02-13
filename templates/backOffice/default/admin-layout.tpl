{* -- By default, check admin login ----------------------------------------- *}

{block name="check-auth"}
    {check_auth role="ADMIN" resource="{block name="check-resource"}{/block}" module="{block name="check-module"}{/block}" access="{block name="check-access"}{/block}" login_tpl="/admin/login"}
{/block}

{block name="no-return-functions"}{/block}

{* -- Define some stuff for Smarty ------------------------------------------ *}
{config_load file='variables.conf'}

{* -- Declare assets directory, relative to template base directory --------- *}
{declare_assets directory='assets'}

{* Set the default translation domain, that will be used by {intl} when the 'd' parameter is not set *}
{default_translation_domain domain='bo.default'}

<!DOCTYPE html>
<html lang="{$lang_code}">
<head>
    <meta charset="utf-8">

    <title>{block name="page-title"}Default Page Title{/block} - {intl l='Thelia Back Office'}</title>

    <link rel="shortcut icon" href="{image file='assets/img/favicon.ico'}" />

    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">

    {block name="meta"}{/block}

    {* -- Bootstrap CSS section --------------------------------------------- *}

    {block name="before-bootstrap-css"}{/block}

    <link rel="stylesheet" href="{stylesheet file='assets/css/styles.css'}">

    {block name="after-bootstrap-css"}{/block}

    {* -- Admin CSS section ------------------------------------------------- *}

    {block name="before-admin-css"}{/block}

    {block name="after-admin-css"}{/block}

    {* Modules css are included here *}

    {hook name="main.head-css" location="head_css" }

    {* HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries *}
    <!--[if lt IE 9]>
    <script src="//html5shim.googlecode.com/svn/trunk/html5.js"></script>
    {javascripts file='assets/js/libs/respond.min.js'}
    <script src="{$asset_url}"></script>
    {/javascripts}
    <![endif]-->
</head>

<body class="push-to-right">
	{* display top bar only if admin is connected *}

	{loop name="top-bar-auth" type="auth" role="ADMIN"}

	    {* -- Brand bar section ------------------------------------------------- *}

		{hook name="main.before-topbar" location="before_topbar" }

		<div class="topbar">
			<div class="container">

		        <div class="row">
		            <div class="col-md-12 clearfix">
		      		    <div class="version-info pull-left">{intl l='Version %ver' ver="{$THELIA_VERSION}"}</div>

                        <div class="clearfix pull-right hidden-xs">
                            <div class="button-toolbar pull-right" role="toolbar">

                                {hook name="main.topbar-top" }

                                <div class="btn-group">
                                    <a href="{navigate to="index"}" title="{intl l='View site'}" target="_blank" class="btn btn-default"><span class="glyphicon glyphicon-eye-open"></span> {intl l="View shop"}</a>
                                    <button class="btn btn-primary"><span class="glyphicon glyphicon-user"></span> {admin attr="firstname"} {admin attr="lastname"}</button>
                                    <button class="btn btn-primary dropdown-toggle" data-toggle="dropdown">
                                        <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right">
                                        <li><a class="profile" href="{url path='admin/configuration/administrators'}"><span class="glyphicon glyphicon-edit"></span> {intl l="Profil"}</a></li>
                                        <li><a class="logout" href="{url path='admin/logout'}" title="{intl l='Close administation session'}"><span class="glyphicon glyphicon-off"></span> {intl l="Logout"}</a></li>
                                    </ul>
                                </div>

                                <div class="btn-group">
                                    {loop type="lang" name="ui-lang" id="{lang attr='id'}"}
                                    <button class="btn btn-default">
                                        <img src="{image file="assets/img/flags/{$CODE}.png"}" alt="{$TITLE}" /> {$CODE|ucfirst}
                                    </button>
                                    {/loop}

                                    <button class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                                        <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu">
                                        {loop type="lang" name="ui-lang"}
                                        <li><a href="{url path="{navigate to="current"}" lang={$CODE}}"><img src="{image file="assets/img/flags/{$CODE}.png"}" alt="{$TITLE}" /> {$CODE|ucfirst}</a></li>
                                        {/loop}
                                     </ul>
                                </div>

                                {loop name="top-bar-search" type="auth" role="ADMIN" resource="admin.search"  access="VIEW"}
                                    <form class="navbar-form pull-right hidden-xs" action="{url path='/admin/search'}">
                                        <div class="form-group">
                                            <input type="text" class="form-control" id="search_term" name="search_term" placeholder="{intl l='Search'}">
                                        </div>
                                        <button type="submit" class="btn btn-default"><span class="glyphicon glyphicon-search"></span></button>
                                    </form>
                                {/loop}


                                {hook name="main.topbar-bottom" }

                            </div>
                        </div>

		            </div>

		    		{hook name="main.inside-topbar" location="inside_topbar" }

		        </div>

		    </div>
		</div>

		{hook name="main.after-topbar" location="after_topbar" }

	    {* -- Top menu section -------------------------------------------------- *}
		{include file="includes/main-menu.html"}

	{/loop}

    {* A basic brandbar is displayed if user is not connected *}

	{elseloop rel="top-bar-auth"}
    <div class="topbar">
        <div class="container">

            <div class="row">
                <div class="col-md-12 clearfix">
                    <div class="version-info pull-left">{intl l='Version %ver' ver="{$THELIA_VERSION}"}</div>
                    <div class="clearfix pull-right hidden-xs">
                        <div class="button-toolbar pull-right" role="toolbar">
                            <div class="btn-group">
                                <a href="{navigate to="index"}" title="{intl l='View site'}" target="_blank" class="btn btn-default"><span class="glyphicon glyphicon-eye-open"></span> {intl l="View shop"}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
	{/elseloop}

    {* -- Main page content section ----------------------------------------- *}
    {hook name="main.before-content" location="before_content"}

	{block name="main-content"}Put here the content of the template{/block}

    {hook name="main.after-content" location="after_content"}
    {* -- Footer section ---------------------------------------------------- *}

    {hook name="main.before-footer" location="before_footer" }

    <hr />
    <footer class="footer">
        <div class="container">
            <p>{intl l='&copy; Thelia 2013'}
            - <a href="http://www.openstudio.fr/" target="_blank">{intl l='Published by OpenStudio'}</a>
            - <a href="http://thelia.net/forum" target="_blank">{intl l='Thelia support forum'}</a>
            - <a href="http://thelia.net/modules" target="_blank">{intl l='Thelia contributions'}</a>
            </p>

            {hook name="main.in-footer" location="in_footer" }

        </div>
    </footer>

    {hook name="main.after-footer" location="after_footer" }

	{* -- Javascript section ------------------------------------------------ *}

	{block name="before-javascript-include"}{/block}
    <script src="http://code.jquery.com/jquery-2.0.3.min.js"></script>
    <script>
        if (typeof jQuery == 'undefined') {
            {javascripts file='assets/js/libs/jquery.js'}
            document.write(unescape("%3Cscript src='{$asset_url}' %3E%3C/script%3E"));
            {/javascripts}
        }
    </script>

	{block name="after-javascript-include"}{/block}

    {javascripts file='assets/js/bootstrap/bootstrap.js'}
        <script src="{$asset_url}"></script>
    {/javascripts}

    {block name="javascript-initialization"}{/block}
    <script>
        (function($) {
            $(document).ready(function(){
                var testModal = $(".modal-force-show");
                if(testModal.length > 0) {
                    testModal.modal("show");
                }

                // Autofocus first form field on modal
                var $modal = $('.modal');
                if ($modal.length > 0) {
                    $modal.on('shown.bs.modal', function() {
                        var $firstField = $('input:visible:first', $modal);
                        console.log($firstField);
                        $firstField.focus();
                    });
                }

                /**
                 * Managment of navigation toggle
                 */
                var $menuLeft = $('#main-navbar'),
                        $showLeftPush = $('#main-navbar-collapse'),
                        $body = $('body');


                $showLeftPush.on('click', function() {
                    $showLeftPush.toggleClass('active');
                    $body.toggleClass('push-to-right');
                    $menuLeft.toggleClass('open').toggleClass('closed');
                });

                /**
                 * Block bootstrap collapse effect on mini navigation
                 */
                $('[data-toggle="collapse"]', $menuLeft).each(function() {
                    var $link = $(this);

                    $link.on('click', $menuLeft, function() {
                        if (!$menuLeft.hasClass('open')) {
                            return false;
                        }
                    });
                });

                /**
                 * Block bootstrap collapse effect on mini navigation
                 */
                $('[data-toggle="collapse"]', $menuLeft).each(function() {
                    var $link = $(this);

                    $link.on('click', $menuLeft, function() {
                        if (!$menuLeft.hasClass('open')) {
                            return false;
                        }
                    });
                });
            });
        })(jQuery);
    </script>

	{* Modules scripts are included now *}
    {hook name='main.footer-js' location="footer_js"}

    {block name="javascript-last-call"}{/block}
    </body>
</html>
