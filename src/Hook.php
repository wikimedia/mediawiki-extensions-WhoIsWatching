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

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Notifications\Hooks\BeforeCreateEchoEventHook;
use MediaWiki\Extension\Notifications\Hooks\EchoGetBundleRulesHook;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\SkinAddFooterLinksHook;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Message\Message;
use MediaWiki\Page\RedirectLookup;
use MediaWiki\Parser\Parser;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;
use Psr\Log\LoggerInterface;
use Skin;
use Wikimedia\Rdbms\LoadBalancer;

class Hook implements
	BeforeCreateEchoEventHook,
	EchoGetBundleRulesHook,
	GetPreferencesHook,
	ParserFirstCallInitHook,
	SkinAddFooterLinksHook
{
	private RedirectLookup $redirectLookup;
	private Config $config;
	private LoadBalancer $loadBalancer;
	private RequestContext $request;
	private UserFactory $userFactory;
	private LoggerInterface $logger;

	public function __construct(
		RedirectLookup $redirectLookup,
		Config $config,
		LoggerInterface $logger,
		LoadBalancer $loadBalancer,
		UserFactory $userFactory
	) {
		$this->redirectLookup = $redirectLookup;
		$this->config = $config;
		$this->logger = $logger;
		$this->loadBalancer = $loadBalancer;
		$this->userFactory = $userFactory;
		$this->request = RequestContext::getMain();
		$this->logger->debug( __METHOD__ );
	}

	/**
	 * Hook to display link to page watchers
	 *
	 * @return true
	 */
	public function onSkinAddFooterLinks(
		Skin $skin, string $group, array &$footerLinks
	) {
		$this->logger->debug( __METHOD__ );
		$title = $skin->getTitle();
		if ( $title && $title->isRedirect() ) {
			$article = $skin->getWikiPage();
			$title = $this->redirectLookup->getRedirectTarget( $article );
		}
		if ( !$title || $title->getNamespace() < 0 ) {
			return true;
		}

		$ret = $this->renderWhoIsWatchingLink( $title );

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
	public function onParserFirstCallInit( $parser ) {
		$this->logger->debug( __METHOD__ );
		$parser->setFunctionHook( 'whoiswatching', [ $this, 'whoIsWatching' ] );
	}

	/**
	 * @param Parser $parser current parser
	 * @param string $pageTitle The title of the page, whoiswatching refers to
	 * @return array
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function whoIsWatching( Parser $parser, $pageTitle ) {
		$this->logger->debug( __METHOD__ );
		$title = Title::newFromDBKey( $pageTitle );
		$output = $this->renderWhoIsWatchingLink( $title );

		return [ $output, 'noparse' => false, 'isHTML' => true ];
	}

	/**
	 * Get the number of watching users for a page
	 *
	 * @param LinkTarget $page for checking
	 * @return null|int number of watching users
	 */
	public function getNumbersOfWhoIsWatching( LinkTarget $page ): ?int {
		$this->logger->debug( __METHOD__ );
		$user = $this->request->getUser();
		$showWatchingUsers = $this->config->get( Config::SHOW_WATCHING_USERS )
			|| $user->isAllowed( Right::SEE_PAGE_WATCHERS );

		if ( $page->getNamespace() >= 0 && $showWatchingUsers ) {
			$dbr = $this->loadBalancer->getConnection( DB_REPLICA );
			$watch = $dbr->selectRow(
				'watchlist', 'COUNT(*) as count', [
					'wl_namespace' => $page->getNamespace(),
					'wl_title' => $page->getDBkey(),
				], __METHOD__
			);
			return $watch->count;
		}
		return null;
	}

	/**
	 * Render the link to Special:WhoIsWatching showing the number of watching users
	 *
	 * @param LinkTarget $page we we want
	 * @return bool
	 */
	public function renderWhoIsWatchingLink( LinkTarget $page ) {
		$this->logger->debug( __METHOD__ );
		$showIfZero = $this->config->get( Config::SHOW_IF_ZERO );
		$count = $this->getNumbersOfWhoIsWatching( $page );

		if ( $count > 0 || ( $showIfZero && $count == 0 ) ) {
			$lang = $this->request->getLanguage();
			return Html::rawElement(
				"span", [ 'class' => 'plainlinks' ],
				Message::newFromSpecifier( 'whoiswatching_users_pageview' )->
					params( $lang->formatNum( (int)$count ), $page )->parse()
			);
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
	public function onBeforeCreateEchoEvent(
		array &$notifications, array &$notificationCategories, array &$icons
	) {
		$this->logger->debug( __METHOD__ );
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
			'user-locators' => [ self::class, 'userLocater' ],
			'presentation-model' => EchoEventPresentationModel::class,
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
	public function onEchoGetBundleRules( Event $event, string &$bundleString ) {
		$this->logger->debug( __METHOD__ );
		switch ( $event->getType() ) {
			case 'whoiswatching-add':
			case 'whoiswatching-remove':
				$bundleString = 'whoiswatching';
				break;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$this->logger->debug( __METHOD__ );
		$option = [
			'type' => 'toggle',
			'label-message' => 'something-something',
			'help-message' => 'something-else',
			'section' => 'watchlist/changeswatchlist',
		];
		$preferences["whoiswatching-page-something"] = $option;
	}

	/**
	 * Get users that should be notified for this event.
	 *
	 * @param Event $event to be looked at
	 * @return array
	 */
	public function userLocater( Event $event ) {
		$this->logger->debug( __METHOD__ );
		$extra = $event->getExtra();
		$user = $this->userFactory->newFromId( $extra['userID'] );
		return [ $user ];
	}
}
