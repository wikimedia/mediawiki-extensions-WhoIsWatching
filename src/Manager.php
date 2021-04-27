<?php

/**
 * Copyright (C) 2018  NicheWork, LLC
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

use Config;
use EchoEvent;
use MediaWiki\MediaWikiServices;
use Title;
use User;

class Manager {

	private $agent;
	private $config;

	/**
	 * @param User $agent user
	 * @param Config $config object
	 */
	public function __construct( User $agent, Config $config ) {
		wfDebugLog( 'WhoIsWatching', __METHOD__ );
		$this->agent = $agent;
		$this->config = $config;
	}

	/**
	 * @param Title $title page updated
	 * @param User $user affected
	 */
	public function addWatch( Title $title, User $user ) {
		wfDebugLog( 'WhoIsWatching', __METHOD__ );
		if ( method_exists( \MediaWiki\Watchlist\WatchlistManager::class, 'addWatch' ) ) {
			// MW 1.37+
			MediaWikiServices::getInstance()->getWatchlistManager()->addWatch( $user, $title );
		} else {
			$user->addWatch( $title );
		}
		$this->eNotifUser( 'add', $title, $user );
	}

	/**
	 * @param Title $title page updated
	 * @param User $user affected
	 */
	public function removeWatch( Title $title, User $user ) {
		wfDebugLog( 'WhoIsWatching', __METHOD__ );
		if ( method_exists( \MediaWiki\Watchlist\WatchlistManager::class, 'removeWatch' ) ) {
			// MW 1.37+
			MediaWikiServices::getInstance()->getWatchlistManager()->removeWatch( $user, $title );
		} else {
			$user->removeWatch( $title );
		}
		$this->eNotifUser( 'remove', $title, $user );
	}

	/**
	 * @param string $action taken
	 * @param Title $title page updated
	 * @param User $user affected
	 */
	public function eNotifUser( $action, Title $title, User $user ) {
		wfDebugLog( 'WhoIsWatching', __METHOD__ );
		$type = $this->config->get( "notificationTypes" );
		wfDebugLog( 'WhoIsWatching', "Called on $action" );
		if ( $type[$action] ) {
			wfDebugLog( 'WhoIsWatching', "Creating event for $action/$title/$user/{$this->agent}" );
			EchoEvent::create( [
				'type' => 'whoiswatching-' . $action,
				'title' => $title,
				'agent' => $this->agent,
				'extra' => [
					'userID' => $user->getID(),
					'revid' => $title->getLatestRevID()
				],
			] );
		}
	}
}
