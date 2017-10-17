<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'WhoIsWatching' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['WhoIsWatching'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['WhoIsWatchingAlias'] = __DIR__ . '/src/i18n/Alias.php';
	wfWarn(
		'Deprecated PHP entry point used for the WhoIsWatching extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the WhoIsWatching extension requires MediaWiki 1.25+' );
}