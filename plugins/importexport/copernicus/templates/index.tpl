{**
 * plugins/importexport/copernicus/index.tpl
 *
 * Copyright (c) 2013-2017 Simon Fraser University
 * Copyright (c) 2003-2016 John Willinsky
 * Copyright (c) 2017 Andriy Semenets 
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * List of operations this plugin can perform
 *
 *}
{strip}
{assign var="pageTitle" value="plugins.importexport.copernicus.displayName"}
{include file="common/header.tpl"}
{/strip}

<br />

<h3>{translate key="plugins.importexport.copernicus.export"}</h3>
<ul>
	<li><a href="{plugin_url path="unregistered"}">{translate key="plugins.importexport.crossref.export.unregistered"}</a></li>
	<li><a href="{plugin_url path="issues"}">{translate key="plugins.importexport.copernicus.export.issue"}</a>: {translate key="plugins.importexport.copernicus.export.issueInfo"}</li>
	<li><a href="{plugin_url path="articles"}">{translate key="plugins.importexport.copernicus.export.article"}</a>: {translate key="plugins.importexport.copernicus.export.articleInfo"}</li>
</ul>
<br />
<a href="http://www.copernicus.org/application/new">{translate key="plugins.importexport.copernicus.export.contact"}</a>: {translate key="plugins.importexport.copernicus.export.contactInfo"}

{include file="common/footer.tpl"}
