<?php
/**
 * Copyright (C) 2017, 2018  NicheWork, LLC
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
 *
 * @author Mark A. Hershberger <mah@nichework.com>
 */

namespace MediaWiki\Extension\WhoIsWatching;

use EchoEvent;
use GlobalVarConfig;
use Html;
use MediaWiki\MediaWikiServices;
use Parser;
use RequestContext;
use Skin;
use Title;
use User;
use WikiPage;

class Hook {
	/**
	 * Hook to display link to page watchers
	 *
	 * @param Skin $sk
	 * @param string $group
	 * @param array &$footerLinks
	 * @return bool
	 */
	public static function onSkinAddFooterLinks(
		Skin $sk, string $group, &$footerLinks
	) {
		$title = $sk->getTitle();
		if ( $title->isRedirect() ) {
			if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
				// MW 1.36+
				$article = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromID( $title->getArticleID() );
			} else {
				$article = WikiPage::newFromID( $title->getArticleID() );
			}
			$title = $article->getRedirectTarget();
		}

		$ret = self::renderWhoIsWatchingLink( $title );

		if ( $ret != false && $group === 'info' ) {
			$footerLinks['number-of-watching-users'] = $ret;
		}

		return true;
	}

	/**
	 * Hook for whoiswatching parser function
	 *
	 * @param Parser $parser current parser
	 */
	public static function onParserSetup( Parser $parser ) {
		$parser->setFunctionHook(
			'whoiswatching', 'MediaWiki\\Extension\\WhoIsWatching\\Hook::whoIsWatching'
		);
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

		return [ $output, 'noparse' => false, 'isHTML' => true ];
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
			$dbr = wfGetDB( DB_REPLICA );
			$watch = $dbr->selectRow(
				'watchlist', 'COUNT(*) as count', [
					'wl_namespace' => $title->getNamespace(),
					'wl_title' => $title->getDBkey(),
				], __METHOD__
			);
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

		if ( $count > 0 || ( $showIfZero && $count == 0 ) ) {
			$lang = RequestContext::getMain()->getLanguage();
			return Html::rawElement( "span", [ 'class' => 'plainlinks' ], wfMessage(
				'whoiswatching_users_pageview', $lang->formatNum( (int)$count ), $title
			)->parse() );
		}

		return false;
	}

	/**
	 * Define the WhoIsWatching notifications
	 *
	 * @param array &$notifications assoc array of notification types
	 * @param array &$notificationCategories assoc array describing
	 *        categories
	 * @param array &$icons assoc array of icons we define
	 */
	public static function onBeforeCreateEchoEvent(
		array &$notifications, array &$notificationCategories, array &$icons
	) {
		wfDebugLog( 'WhoIsWatching', __METHOD__ );
		$icons['whoiswatching']['path'] = 'WhoIsWatching/assets/WhoIsWatching.svg';

		$notifications['whoiswatching-add'] = [
			'bundle' => [
				'web' => true,
				'email' => true,
				'expandable' => true,
			],
			'title-message' => 'whoiswatching-add-title',
			'category' => 'whoiswatching',
			'group' => 'neutral',
			'user-locators' => [ 'MediaWiki\\Extension\\WhoIsWatching\\Hook::userLocater' ],
			'presentation-model' => 'MediaWiki\\Extension\\WhoIsWatching\\EchoEventPresentationModel',
		];

		$notifications['whoiswatching-remove'] = [
			'bundle' => [
				'web' => true,
				'email' => true,
				'expandable' => true,
			],
			'title-message' => 'whoiswatching-remove-title',
			'category' => 'whoiswatching',
			'group' => 'neutral',
			'user-locators' => [ 'MediaWiki\\Extension\\WhoIsWatching\\Hook::userLocater' ],
			'presentation-model' => 'MediaWiki\\Extension\\WhoIsWatching\\EchoEventPresentationModel',
		];

		$notificationCategories['whoiswatching'] = [
			'priority' => 2,
			'tooltip' => 'echo-pref-tooltip-whoiswatching'
		];
	}

	/**
	 * @param Event $event to bundle
	 * @param string &$bundleString to use
	 */
	public static function onEchoGetBundleRules( EchoEvent $event, &$bundleString ) {
		wfDebugLog( 'WhoIsWatching', __METHOD__ );
		switch ( $event->getType() ) {
			case 'whoiswatching-add':
			case 'whoiswatching-remove':
				$bundleString = 'whoiswatching';
				break;
		}
	}

	/**
	 * Get users that should be notified for this event.
	 *
	 * @param EchoEvent $event to be looked at
	 * @return array
	 */
	public static function userLocater( EchoEvent $event ) {
		wfDebugLog( 'WhoIsWatching', __METHOD__ );
		$extra = $event->getExtra();
		$user = User::newFromID( $extra['userID'] );
		return [ $user ];
	}

	/**
	 * Static function to help us determine if WiW is available.
	 *
	 * @return string
	 */
	public static function getVersion() {
		$extension = json_decode( file_get_contents( __DIR__ . "/../extension.json" ) );
		return $extension->version;
	}
}
