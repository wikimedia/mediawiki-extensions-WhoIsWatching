<?php
/**
 * Copyright (C) 2017  Mark A. Hershberger
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace WhoIsWatching;

use Article;
use GlobalVarConfig;
use Parser;
use QuickTemplate;
use RequestContext;
use Skin;
use Title;

class Hook {
	/**
	 * Hook to display link to page watchers
	 *
	 * @param Skin $template skin
	 * @param QuickTemplate $tpl template
	 * @return bool
	 */
	public static function onSkinTemplateOutputPageBeforeExec(
		Skin $template, QuickTemplate $tpl
	) {
		$title = $template->getTitle();
		if ( $title->isRedirect() ) {
			$article = Article::newFromID( $title->getArticleID() );
			$title = $article->getRedirectTarget();
		}

		$ret = self::renderWhoIsWatchingLink( $title );

		if ( $ret != false ) {
			$tpl->set( 'numberofwatchingusers', $ret );
		}

		return true;
	}

	/**
	 * Hook for whoiswatching parser function
	 *
	 * @param Parser $parser current parser
	 */
	public static function onParserSetup( Parser $parser ) {
		$parser->setFunctionHook( 'whoiswatching', 'WhoIsWatching\\Hook::whoIsWatching' );
	}

	/**
	 * @param Parser $parser current parser
	 * @param string $pageTitle The title of the page, whoiswatching refers to
	 * @return array
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public static function whoIsWatching( Parser $parser, $pageTitle ) {
		$title = Title::newFromDBKey( $pageTitle );
		$output = self::renderWhoIsWatchingLink( $title );

		return [ $output, 'noparse' => false,'isHTML' => true ];
	}

	/**
	 * Get the number of watching users for a page
	 *
	 * @param Title $title for checking
	 * @param GlobalVarConfig $conf configuration
	 * @return null/number of watching users
	 */
	public static function getNumbersOfWhoIsWatching( Title $title, GlobalVarConfig $conf ) {
		$user = RequestContext::getMain()->getUser();
		$showWatchingUsers = $conf->get( "showwatchingusers" )
						   || $user->isAllowed( 'seepagewatchers' );

		if ( $title->getNamespace() >= 0 && $showWatchingUsers ) {
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select( 'watchlist', 'COUNT(*) as count', [
				'wl_namespace' => $title->getNamespace(),
				'wl_title'     => $title->getDBkey(),
			], __METHOD__ );
			$watch = $dbr->fetchObject( $res );

			return $watch->count;
		}
		return null;
	}

	/**
	 * Render the link to Special:WhoIsWatching showing the number of watching users
	 *
	 * @param Title $title we want
	 * @return bool
	 */
	public static function renderWhoIsWatchingLink( Title $title ) {
		$conf = new GlobalVarConfig( "whoiswatching_" );
		$showIfZero = $conf->get( "showifzero" );

		$count = self::getNumbersOfWhoIsWatching( $title, $conf );

		if ( $count > 0 || $showIfZero ) {
			$lang = RequestContext::getMain()->getLanguage();
			return wfMessage(
				'whoiswatching_users_pageview', $lang->formatNum( $count ), $title
			)->parse();
		}

		return false;
	}
}
