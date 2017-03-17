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

use GlobalVarConfig;
use QuickTemplate;
use RequestContext;
use Skin;

class Hook {
	/**
	 * Hook to display link to page watchers
	 * @param Skin $template skin
	 * @param QuickTemplate $tpl template
	 * @return boolean
	 */
	public static function onSkinTemplateOutputPageBeforeExec(
		Skin $template, QuickTemplate $tpl
	) {
		$conf = new GlobalVarConfig( "whoiswatching_" );
		$showIfZero = $conf->get( "showifzero" );
		$title = $template->getTitle();
		if ( $title->isRedirect() ) {
			$article = Article::newFromID( $title->getArticleID() );
			$title = $article->getRedirectTarget();
		}
		$user = $template->getUser();
		$showWatchingUsers = $conf->get( "showwatchingusers" )
						   || $user->isAllowed( 'seepagewatchers' );

		if ( $title->getNamespace() >= 0 && $showWatchingUsers ) {
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select( 'watchlist', 'COUNT(*) as count', [
								 'wl_namespace' => $title->getNamespace(),
								 'wl_title' => $title->getDBkey(),
			], __METHOD__ );
			$watch = $dbr->fetchObject( $res );
			if ( $watch->count > 0 || $showIfZero ) {
				$lang = RequestContext::getMain()->getLanguage();
				$msg = wfMessage( 'whoiswatching_users_pageview',
								  $lang->formatNum( $watch->count ), $title
				)->parse();
				$tpl->set( 'numberofwatchingusers', $msg );
			}
		}

		return true;
	}
}
