<?php

/**
 * Copyright (C) 2018, 2025  NicheWork, LLC
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

use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\Watchlist\WatchlistManager;
use Psr\Log\LoggerInterface;

class Manager {

	private User $agent;
	private Config $config;
	private WatchlistManager $watchListManager;
	private LoggerInterface $logger;

	/**
	 * @param User $agent user
	 * @param Config $config object
	 */
	public function __construct( User $agent, Config $config ) {
		$this->agent = $agent;
		$this->config = $config;
		$this->watchListManager = MediaWikiServices::getInstance()->getWatchlistManager();
		$this->logger = LoggerFactory::getInstance( "WhoIsWatching" );
	}

	/**
	 * @param Title $title page updated
	 * @param User $user affected
	 */
	public function addWatch( Title $title, User $user ) {
		$this->logger->debug( __METHOD__ );
		$this->watchListManager->addWatch( $user, $title );
		$this->eNotifUser( 'add', $title, $user );
	}

	/**
	 * @param Title $title page updated
	 * @param User $user affected
	 */
	public function removeWatch( Title $title, User $user ) {
		$this->logger->debug( __METHOD__ );
		$this->watchListManager->removeWatch( $user, $title );
		$this->eNotifUser( 'remove', $title, $user );
	}

	/**
	 * @param string $action taken
	 * @param Title $title page updated
	 * @param User $user affected
	 */
	public function eNotifUser( string $action, Title $title, User $user ) {
		$this->logger->debug( __METHOD__ );
		$type = $this->config->get( Config::NOTIFICATION_TYPES );
		$this->logger->debug( "Called on $action" );
		if ( $type[$action] ) {
			$this->logger->debug( "Creating event for $action/$title/$user/{$this->agent}" );
			Event::create( [
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
