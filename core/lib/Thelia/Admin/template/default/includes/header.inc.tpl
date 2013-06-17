<!DOCTYPE html>
<html lang="{$lang}">
<head>
	<title>{intl l='Thelia Back Office'}{if ! empty($page_title)} - {$page_title}{/if}</title>

	{images file='../assets/img/favicon.ico'}<link rel="shortcut icon" href="{$asset_url}" />{/images}

	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	{stylesheets file='../assets/css/*' filters='less,cssrewrite'}
		<link rel="stylesheet" href="{$asset_url}">
	{/stylesheets}

	{stylesheets file='../assets/bootstrap/css/bootstrap.min.css' filters='cssrewrite'}
		<link rel="stylesheet" href="{$asset_url}">
	{/stylesheets}

	{stylesheets file='../assets/bootstrap/css/bootstrap-responsive.min.css' filters='cssrewrite'}
		<link rel="stylesheet" href="{$asset_url}">
	{/stylesheets}

	{* TODO allow modules to include CSS here *}
</head>
<body>