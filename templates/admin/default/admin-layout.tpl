{* -- By default, check admin login ----------------------------------------- *}

{block name="check-auth"}
    {check_auth roles="ADMIN" permissions="{block name="check-permissions"}{/block}" login_tpl="/admin/login"}
{/block}

<!DOCTYPE html>
<html lang="{$lang_code}">
<head>
    <title>{block name="page-title"}Default Page Title{/block} - {intl l='Thelia Back Office'}</title>

    {images file='assets/img/favicon.ico'}<link rel="shortcut icon" href="{$asset_url}" />{/images}

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {block name="meta"}{/block}

    {* -- Bootstrap CSS section --------------------------------------------- *}

    {block name="before-bootstrap-css"}{/block}
	
	{stylesheets file='assets/less/*' filters='less,cssembed'}
        <link rel="stylesheet" href="{$asset_url}">
    {/stylesheets}

    {block name="after-bootstrap-css"}{/block}

    {* -- Admin CSS section ------------------------------------------------- *}

    {block name="before-admin-css"}{/block}

    {block name="after-admin-css"}{/block}

    {* Modules css are included here *}

    {module_include location='head_css'}
</head>

<body>
	{* display top bar only if admin is connected *}

	{loop name="top-bar-auth" type="auth" roles="ADMIN"}

	    {* -- Brand bar section ------------------------------------------------- *}

		{module_include location='before_topbar'}

		<div class="topbar">
			<div class="container">
		        
		        <div class="row">
		            <div class="col-md-6">
		      		    <div class="version-info">{intl l='Version %ver' ver="{$THELIA_VERSION}"}</div>
		            </div>

		    		{module_include location='inside_topbar'}  		
		            
		            <div class="col-md-6 clearfix">                

		                <div class="btn-group pull-right">
		                    <a href="{navigate to="index"}" title="{intl l='View site'}" target="_blank" class="btn btn-default"><span class="glyphicon glyphicon-eye-open"></span> {intl l="View shop"}</a>
		                    <button class="btn btn-default btn-primary"><span class="glyphicon glyphicon-user"></span> {admin attr="firstname"} {admin attr="lastname"}</button>
		                    <button class="btn btn-default btn-primary dropdown-toggle" data-toggle="dropdown">
		                        <span class="caret"></span>
		                    </button>
		                    <ul class="dropdown-menu">
		                        <li><a class="profile" href="{url path='admin/edit_profile'}"><span class="glyphicon glyphicon-edit"></span> {intl l="Profil"}</a></li>                        
		                        <li><a class="logout" href="{url path='admin/logout'}" title="{intl l='Close administation session'}"><span class="glyphicon glyphicon-off"></span> {intl l="Logout"}</a></li>
		                    </ul>
		                </div>               

		            </div>
		        </div>
		  		
		    </div>
		</div>

		{module_include location='after_topbar'}

	    {* -- Top menu section -------------------------------------------------- *}

		{module_include location='before_top_menu'}

		<nav class="navbar navbar-default" role="navigation">
			
            <div class="container">

    			<div class="navbar-header">
    				<button type="button" class="btn navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
    					<span class="sr-only">Toggle navigation</span>
    					<span class="icon-bar"></span>
    					<span class="icon-bar"></span>
    					<span class="icon-bar"></span>
    				</button>			
    			</div>

    			<div class="collapse navbar-collapse navbar-collapse">
    				<ul class="nav navbar-nav">

                        <li class="{if $admin_current_location == 'home'}active{/if}" id="home_menu">
                            <a href="{url path='/admin/home'}">{intl l="Home"}</a>
                        </li>

                        {loop name="menu-auth-customer" type="auth" roles="ADMIN" permissions="admin.customers.view"}
                        <li class="{if $admin_current_location == 'customer'}active{/if}" id="customers_menu">
                            <a href="{url path='/admin/customers'}">{intl l="Customers"}</a>
                        </li>
                        {/loop}

                        {loop name="menu-auth-order" type="auth" roles="ADMIN" permissions="admin.orders.view"}
                        <li class="dropdown {if $admin_current_location == 'customer'}active{/if}" id="orders_menu" data-toggle="dropdown">

                            <a href="#">{intl l="Orders"} <span class="caret"></span></a>

                             <ul class="dropdown-menu config_menu" role="menu">

                                <li role="menuitem"><a data-target="{url path='admin/orders'}" href="{url path='admin/orders'}">
                                    {intl l="All orders"}
                                    <span class="badge badge-important">{count type="order"}</span></a>
                                </li>

                                {loop name="order-status-list" type="order-status"}
                                <li role="menuitem">
                                    <a data-target="{url path='admin/orders/$LABEL'}" href="{url path='admin/orders/$LABEL'}">
                                        {$LABEL} <span class="badge badge-important">{count type="order" status="{$ID}"}</span>
                                    </a>
                                </li>
                                {/loop}
                            </ul>
                        </li>
                        {/loop}

                        {loop name="menu-auth-catalog" type="auth" roles="ADMIN" permissions="admin.catalog.view"}
                        <li class="{if $admin_current_location == 'catalog'}active{/if}" id="catalog_menu">
                            <a href="{url path='/admin/catalog'}">{intl l="Catalog"}</a>
                        </li>
                        {/loop}

                        {loop name="menu-auth-content" type="auth" roles="ADMIN" permissions="admin.content.view"}
                        <li class="{if $admin_current_location == 'content'}active{/if}" id="content_menu">
                            <a href="{url path='/admin/content'}">{intl l="Content"}</a>
                        </li>
                        {/loop}

                        {loop name="menu-auth-discount" type="auth" roles="ADMIN" permissions="admin.discount.view"}
                        <li class="{if $admin_current_location == 'discount'}active{/if}" id="discount_menu">
                            <a href="{url path='/admin/discount'}">{intl l="Discount"}</a>
                        </li>
                        {/loop}

                        {loop name="menu-auth-config" type="auth" roles="ADMIN" permissions="admin.config.view"}
                        <li class="{if $admin_current_location == 'configuration'}active{/if}" id="config_menu">
                            <a href="{url path='/admin/configuration'}">{intl l="Configuration"}</a>
                        </li>
                        {/loop}

                        {loop name="menu-auth-modules" type="auth" roles="ADMIN" permissions="admin.modules.view"}
                        <li class="{if $admin_current_location == 'modules'}active{/if}" id="modules_menu">
                            <a href="{url path='/admin/modules'}">{intl l="Modules"}</a>
                        </li>

                        {module_include location='in_top_menu_items'}

                        {/loop}
                    </ul>

    			</div>
            </div>
		</nav>

		{module_include location='after_top_menu'}

	{/loop}

    {* A basic brandbar is displayed if user is not connected *}

	{elseloop rel="top-bar-auth"}
	    <div class="brandbar brandbar-wide container">
	        <a class="navbar-brand" href="{url path='/admin'}">{images file='assets/img/logo-thelia-34px.png'}<img src="{$asset_url}" alt="{intl l='Thelia, solution e-commerce libre'}" />{/images}</a>
	    </div>
	{/elseloop}

    {* -- Main page content section ----------------------------------------- *}

	{block name="main-content"}Put here the content of the template{/block}

    {* -- Footer section ---------------------------------------------------- *}

    {module_include location='before_footer'}

    <hr />
    <footer class="footer">
        <div class="container">
            <p>{intl l='&copy; Thelia 2013'}
            - <a href="http://www.openstudio.fr/" target="_blank">{intl l='Édité par OpenStudio'}</a>
            - <a href="http://forum.thelia.net/" target="_blank">{intl l='Forum Thelia'}</a>
            - <a href="http://contrib.thelia.net/" target="_blank">{intl l='Contributions Thelia'}</a>
                  <span class="pull-right">{intl l='interface par <a target="_blank" href="http://www.steaw-webdesign.com/">Steaw-Webdesign</a>'}</span>
            </p>

            {module_include location='in_footer'}

        </div>
    </footer>

    {module_include location='after_footer'}


	{* -- Javascript section ------------------------------------------------ *}

	{block name="before-javascript-include"}{/block}

	<script src="http://code.jquery.com/jquery-2.0.3.min.js"></script>

	{block name="after-javascript-include"}{/block}	

    {block name="javascript-initialization"}{/block}

	{* Modules scripts are included now *}
	{module_include location='footer_js'}

    {javascripts file='assets/js/bootstrap/bootstrap.js'}
        <script src="{$asset_url}"></script>
    {/javascripts}
</body>
</html>