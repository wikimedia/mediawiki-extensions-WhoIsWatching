<?php

if ( PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg' ) {
	die( 'Not an entry point' );
}

error_reporting( E_ALL | E_STRICT );
date_default_timezone_set( 'UTC' );
ini_set( 'display_errors', 1 );

if ( !class_exists( 'MediaWiki\\Extension\\WhoIsWatching\\Hook' ) ) {
	die( "\nWhoIsWatching is not available, please check your Composer or LocalSettings.\n" );
}
$version = MediaWiki\Extension\WhoIsWatching\Hook::getVersion();

print sprintf( "\n%-20s%s\n", "WhoIsWatching: ", $version );
