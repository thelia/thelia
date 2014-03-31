<nav class="nav-breadcrumb" aria-labelledby="breadcrumb-label" role="navigation">
                    <strong id="breadcrumb-label">{intl l="You are here:"}</strong>
                    <ul class="breadcrumb" itemprop="breadcrumb">
                        <li itemscope itemtype="http://data-vocabulary.org/Breadcrumb"><a href="{navigate to="index"}" itemprop="url"><span itemprop="title">{intl l="Home"}</span></a></li>
{foreach $breadcrumbs as $breadcrumb}
{if $breadcrumb.title}
{if $breadcrumb@last}
                        <li class="active" itemscope itemtype="http://data-vocabulary.org/Breadcrumb"><span itemprop="title">{$breadcrumb.title|unescape}</span></li>
{else}
                        <li itemscope itemtype="http://data-vocabulary.org/Breadcrumb"><a href="{$breadcrumb.url|default:'#' nofilter}" title="{$breadcrumb.title|unescape}" itemprop="url"><span itemprop="title">{$breadcrumb.title|unescape}</span></a></li>
{/if}
{/if}
{/foreach}
                    </ul>
                </nav><!-- /.nav-breadcrumb -->
