<?php
# Alert the user that this is no longer a valid entry point to MediaWiki
echo <<<EOT
WhoIsWatching no longer uses PHP's require statement to load.  This
version requires at least MediaWiki 1.26.  Versions of this extension
compatible with earlier versions of MediaWiki can be found at
https://www.mediawiki.org/wiki/Special:ExtensionDistributor/WhoIsWatching

To install WhoIsWatching extension, put the following line in LocalSettings.php:
wfLoadExtension( "WhoIsWatching" );
EOT;
exit( 1 );
