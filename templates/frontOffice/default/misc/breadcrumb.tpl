<nav class="nav-breadcrumb" role="navigation" aria-labelledby="breadcrumb-label">
    <strong id="breadcrumb-label" class="sr-only">{intl l="You are here:"}</strong>

    <ul class="breadcrumb" itemscope itemtype="http://schema.org/BreadcrumbList">
        <li itemscope itemtype="http://schema.org/ListItem" itemprop="itemListElement" ><a href="{navigate to="index"}" itemprop="item">
            <span itemprop="name">{intl l="Home"}</span></a>
            <meta itemprop="position" content="1">
        </li>
        {foreach $breadcrumbs as $breadcrumb}
            {if $breadcrumb.title}
                <li itemscope itemtype="http://schema.org/ListItem" itemprop="itemListElement"{if $breadcrumb@last} class="active"{/if}>
                    <a href="{$breadcrumb.url|default:'#' nofilter}" title="{$breadcrumb.title|unescape}" itemprop="item"><span itemprop="name">{$breadcrumb.title|unescape}</span></a>
                    <meta itemprop="position" content="{$breadcrumb@key+2}">
                </li>
            {/if}
        {/foreach}
    </ul>
</nav><!-- /.nav-breadcrumb -->
