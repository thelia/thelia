<!DOCTYPE html>
<html lang="{$lang}">
<head>

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