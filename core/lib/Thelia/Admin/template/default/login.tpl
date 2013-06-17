{$page_title={intl l='Thelia'}}
{include file='includes/header.inc.tpl'}

<div class="loginpage">

   	<div class="brandbar container">
       	<a class="brand" href="index.php">{images file='assets/img/logo-thelia-34px.png'}<img src="{$asset_url}" alt="{intl l='Thelia, solution e-commerce libre'}" />{/images}</a>
    </div>

    <div id="wrapper" class="container">

	    {thelia_module action='index_top'}

	   	<div class="hero-unit">
	        <h1>{intl l='Thelia Back Office'}</h1>

			<form action="/admin/login" method="post" class="well form-inline">
				<input type="text" class="input" placeholder="{intl l='E-mail address'}" name="username" />
				<input type="password" class="input" placeholder="{intl l='Password'}" name="password" />

				<label class="checkbox"> <input type="checkbox" name="remember" value="yes"> {intl l='Remember me'}</label>

				<button type="submit" class="btn btn-primary">{intl l='Login'} <i class="icon-play"></i></button>
			</form>
		</div>

		<div class="row-fluid">

		</div>
	</div>

	{thelia_module action='index_bottom'}
</div>

{include file='includes/footer.inc.tpl'}