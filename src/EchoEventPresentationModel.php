<?php

/**
 * Who is watching events
 *
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

use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use Wikimedia\Timestamp\TimestampException;

class EchoEventPresentationModel extends \MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel {
	/**
	 * Tell the caller if this event can be rendered.
	 *
	 * @return bool
	 */
	public function canRender() {
		wfDebugLog( 'WhoIsWatching', __METHOD__ );
		return (bool)$this->event->getTitle();
	}

	/**
	 * Which of the registered icons to use.
	 *
	 * @return string
	 */
	public function getIconType() {
		wfDebugLog( 'WhoIsWatching', __METHOD__ );
		return 'whoiswatching';
	}

	/**
	 * The header of this event's display
	 *
	 * @return Message
	 */
	public function getHeaderMessage() {
		wfDebugLog( 'WhoIsWatching', __METHOD__ );
		if ( $this->isBundled() ) {
			$msg = $this->msg( 'whoiswatching-notification-bundle' );
			$msg->params( $this->getBundleCount() );
			$msg->params( $this->getTruncatedTitleText( $this->event->getTitle(), true ) );
			$msg->params( $this->getViewingUserForGender() );
		} else {
			$msg = $this->msg( 'whoiswatching-notification-' . $this->event->getType() . '-header' );
			$msg->params( $this->getPageTitle() );
			$msg->params( $this->getTruncatedTitleText( $this->getPageTitle(), true ) );
			$msg->params( $this->event->getTitle() );
			$msg->params( $this->getTruncatedTitleText( $this->event->getTitle(), true ) );
		}
		return $msg;
	}

	/**
	 * Shorter display
	 *
	 * @return Message
	 */
	public function getCompactHeaderMessage() {
		wfDebugLog( 'WhoIsWatching', __METHOD__ );
		$msg = parent::getCompactHeaderMessage();
		$msg->params( $this->getViewingUserForGender() );
		return $msg;
	}

	/**
	 * Summary of edit
	 *
	 * @return string
	 */
	public function getRevisionEditSummary() {
		wfDebugLog( 'WhoIsWatching', __METHOD__ );
		$msg = $this->getMessageWithAgent(
			'whoiswatching-notification-' . $this->event->getType() . '-summary'
		);
		$msg->params( $this->getPageTitle() );
		$msg->params( $this->getTruncatedTitleText( $this->getPageTitle(), true ) );
		$msg->params( $this->event->getTitle() );
		$msg->params( $this->getTruncatedTitleText( $this->event->getTitle(), true ) );
		return $msg;
	}

	/**
	 * Body to display
	 *
	 * @return Message
	 */
	public function getBodyMessage() {
		wfDebugLog( 'WhoIsWatching', __METHOD__ );
		$msg = $this->getMessageWithAgent(
			'whoiswatching-notification-' . $this->event->getType() . '-body'
		);
		$msg->params( $this->getPageTitle() );
		$msg->params( $this->event->getTitle() );
		return $msg;
	}

	/**
	 * @return Title
	 */
	public function getPageTitle() {
		wfDebugLog( 'WhoIsWatching', __METHOD__ );
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromID( $this->event->getExtraParam( 'pageid' ) );
		return $page ? $page->getTitle() : Title::makeTitle( NS_SPECIAL, 'Badtitle/' . __METHOD__ );
	}

	/**
	 * Provide the main link
	 *
	 * @return array
	 */
	public function getPrimaryLink() {
		wfDebugLog( 'WhoIsWatching', __METHOD__ );
		$title = $this->event->getTitle();
		$msg = $this->msg( 'whoiswatching-notification-link' );
		$msg->params( $title );
		return [
			'url' => $title->getFullURL(),
			'label' => $title->getPrefixedText()
		];
	}

	/**
	 * Aux links
	 *
	 * @return array
	 */
	public function getSecondaryLinks() {
		wfDebugLog( 'WhoIsWatching', __METHOD__ );
		if ( $this->isBundled() ) {
			// For the bundle, we don't need secondary actions
			return [];
		} else {
			return [
				$this->getAgentLink(),
				[
					'url' => $this->getPageTitle()->getFullURL(),
					'label' => $this->getPageTitle()->getPrefixedText()
				]
			];
		}
	}

	/**
	 * override parent
	 * @return array
	 * @throws TimestampException
	 */
	public function jsonSerialize(): array {
		wfDebugLog( 'WhoIsWatching', __METHOD__ );
		$body = $this->getBodyMessage();

		return [
			'header' => $this->getHeaderMessage()->parse(),
			'compactHeader' => $this->getCompactHeaderMessage()->parse(),
			'body' => $body ? $body->plain() : '',
			'icon' => $this->getIconType(),
			'links' => [
				'primary' => $this->getPrimaryLinkWithMarkAsRead() ?: [],
				'secondary' => array_values( array_filter( $this->getSecondaryLinks() ) ),
			],
		];
	}
}
