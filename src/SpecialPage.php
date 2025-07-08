<?php
/**
 * Special page for WhoIsWatching
 *
 * Copyright (C) 2017  NicheWork, LLC
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

use ErrorPageError;
use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

class SpecialPage extends \MediaWiki\SpecialPage\SpecialPage {

	private $targetPage = null;
	private $targetUser = null;
	private $nameType;
	private $allowAddingPeople;
	private $allowRemovingPeople;
	private $showWatchingUsers;
	private $wiw;

	public function __construct() {
		parent::__construct( 'WhoIsWatching' );

		$conf = new GlobalVarConfig( "whoiswatching_" );
		$user = $this->getUser();
		$this->nameType = $conf->get( "nametype" );

		$this->allowAddingPeople
			= ( !$user->isAnon() && $conf->get( "allowaddingpeople" ) )
			|| $user->isAllowed( "addpagetoanywatchlist" );
		$this->allowRemovingPeople
			= ( !$user->isAnon() && $conf->get( "allowaddingpeople" ) )
			|| $user->isAllowed( "removepagefromanywatchlist" );
		$this->showWatchingUsers
			= ( !$user->isAnon() && $conf->get( "showwatchingusers" ) )
			|| $user->isAllowed( "seepagewatchers" );
		$this->wiw = new Manager( $this->getUser(), $conf );
	}

	/**
	 * Page executioner
	 *
	 * @param string $par page we're messing with
	 * @return bool
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$this->checkPermissions();
		$this->outputHeader();

		if ( $this->getTargetPage( $par ) ) {
			if ( $this->addWatchersForm() ) {
				return true;
			}
			$this->showWatchingUsers();
		}
	}

	/**
	 * Add to upstream check permissions
	 *
	 * @return void
	 * @throws ErrorPageError
	 */
	public function checkPermissions() {
		if (
			$this->showWatchingUsers
			|| $this->allowRemovingPeople
			|| $this->allowAddingPeople
		) {
			return parent::checkPermissions();
		}
		throw new ErrorPageError(
			"whoiswatching-permission-denied-title",
			"whoiswatching-permission-denied",
			[ $this->getLanguage()->commaList(
				[
					"addpagetoanywatchlist",
					"removepagefromanywatchlist",
					"seepagewatchers"
				]
			) ]
		);
	}

	/**
	 * Either return the page chosen or display a page chooser and return false
	 *
	 * @param string $par the passed in parameter
	 * @return bool|Title
	 * @throws ErrorPageError
	 */
	private function getTargetPage( $par ) {
		$req = $this->getRequest();
		$title = $req->getVal( 'page' );
		if ( !$title && !$par ) {
			return $this->pickPage();
		}

		if ( $title ) {
			$namespaceInfo = MediaWikiServices::getInstance()->getNamespaceInfo();
			$nsRevLookup = array_flip( $namespaceInfo->getCanonicalNamespaces() );
			$nameSpace = $req->getVal( 'ns', '' );
			if ( ctype_digit( $nameSpace ) ) {
				$nameSpace = intval( $nameSpace );
			} else {
				$nameSpace = $nsRevLookup[ $nameSpace ] ?? 0;
			}
			$this->targetPage = Title::newFromText( $title, $nameSpace );
		} else {
			$this->targetPage = Title::newFromText( $par );
		}
		$this->targetUser = User::newFromName( $req->getVal( 'user' ) );

		if ( !$this->targetPage ) {
			throw new ErrorPageError(
				"whoiswatching-usage-title", "specialwhoiswatchingusage"
			);
		}
		$nameSpace = $this->targetPage->getNamespace();
		if ( $nameSpace < 0 ) {
			throw new ErrorPageError(
				"whoiswatching-not-possible-title",
				"whoiswatching-not-possible",
				[ $this->targetPage ]
			);
		}

		return true;
	}

	/**
	 * The form for adding a watcher.
	 *
	 * @return bool
	 */
	private function addWatchersForm() {
		if ( $this->allowAddingPeople === false ) {
			return false;
		}
		if ( !$this->targetPage->exists() ) {
			$this->getOutput()->addHTML( '<b>This page does not (yet) exist!</b>' );
		}
		$formDescriptor = [
			'user' => [
				'type' => 'user',
				'name' => 'user',
				'label-message' => 'whoiswatching-user-editname',
				'size' => 30,
				'id' => 'username',
				'autofocus' => true,
				'value' => '',
				'required' => true
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
			->addHiddenField( 'addToken', $this->getUser()->getEditToken( __CLASS__ ) )
			->setAction( $this->getPageTitle( $this->targetPage )->getLocalUrl() )
			->setId( 'mw-whoiswatching-form1' )
			->setMethod( 'post' )
			->setName( 'uluser' )
			->setSubmitTextMsg( 'whoiswatching-adduser' )
			->setWrapperLegendMsg( 'whoiswatching-lookup-user' )
			->prepareForm()
			->displayForm( false );

		if ( $this->targetUser ) {
			$this->maybeAddWatcher();
		}
		return false;
	}

	/**
	 * Add a watcher if the request did so
	 *
	 * @return bool
	 * @throws ErrorPageError
	 */
	private function maybeAddWatcher() {
		$req = $this->getRequest();
		$token = $req->getVal( 'addToken' );
		if ( $req->wasPosted() && $token ) {
			if ( $this->getUser()->matchEditToken( $token, __CLASS__ ) ) {
				$title = $this->targetPage;
				$this->getOutput()->redirect(
					$this->getPageTitle( $title )->getLocalUrl()
				);
				return $this->wiw->addWatch(
					$title, $this->targetUser
				);
			}

			throw new ErrorPageError(
				'sessionfailure-title', 'sessionfailure'
			);
		}
		return true;
	}

	/**
	 * If the user selected a page, redirect to work on it.  If not,
	 * show the form.
	 *
	 * @return bool
	 */
	private function pickPage() {
		$target = Title::newFromText( $this->getRequest()->getVal( "target" ) );
		if ( $target ) {
			$this->getOutput()->redirect(
				$this->getPageTitle( $target )->getLocalUrl()
			);
			return false;
		}
		$formDescriptor = [
			'page' => [
				'type' => 'title',
				'name' => 'target',
				'label-message' => 'whoiswatching-title',
				'size' => 40,
				'id' => 'whoiswatching-target',
				'value' => str_replace( '_', ' ', $this->targetPage ),
				'cssclass' => 'mw-searchInput',
				'required' => true
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
			->setAction( $this->getPageTitle( $target )->getLocalUrl() )
			->setMethod( 'get' )
			->setName( 'uluser' )
			->setSubmitID( 'mw-whoiswatching-form1' )
			->setSubmitTextMsg( 'whoiswatching-select-title' )
			->setWrapperLegendMsg( 'whoiswatching-lookup-title' )
			->prepareForm()
			->displayForm( false );

		return false;
	}

	/**
	 * Remove any posted for removal
	 *
	 * @param array $formData posted data
	 */
	private function maybeRemoveWatcher( array $formData ) {
		$redir = false;
		foreach ( $formData as $watcherID => $remove ) {
			if ( $remove ) {
				$this->wiw->removeWatch(
					$this->targetPage,
					User::newFromId( $watcherID )
				);
				$redir = true;
			}
		}
		if ( $redir ) {
			// reload page
			$this->getOutput()->redirect(
				$this->getPageTitle( $this->targetPage )->getLocalUrl()
			);
		}
	}

	/**
	 * Show watching users if we can
	 *
	 * @return null
	 */
	private function showWatchingUsers() {
		if ( !$this->allowRemovingPeople && !$this->showWatchingUsers ) {
			return;
		}
		$out = $this->getOutput();
		$out->addWikiTextAsInterface(
			"== " . wfMessage( 'specialwhoiswatchingpage' )
			->params( $this->targetPage )->plain() . " =="
		);

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$watchingusers = [];
		$res = $dbr->select(
			'watchlist', 'wl_user',
			[ 'wl_namespace' => $this->targetPage->getNamespace(),
			  'wl_title' => $this->targetPage->getDBkey() ], __METHOD__
		);
		foreach ( $res as $row ) {
			$u = User::newFromID( $row->wl_user );
			$key = $u->mId;
			$display = $u->getRealName();
			if ( ( $this->nameType == 'UserName' ) || !$u->getRealName() ) {
				$display = $u->getName();
			}
			$watchingusers[$key] = $display;
		}

		asort( $watchingusers );

		$users = [];
		foreach ( $watchingusers as $id => $link ) {
			$users[ $id ] = [ 'type' => 'check', 'label' => $link ];
		}
		if ( $this->allowRemovingPeople ) {
			$form = HTMLForm::factory( 'ooui', $users, $this->getContext() );
			$form->setSubmitText( $this->msg( 'whoiswatching-deluser' )->text() );
			$form->setSubmitCallback(
				function ( $formData, $form ) {
					return $this->maybeRemoveWatcher( $formData );
				} );
			$form->setSubmitDestructive();
			$form->show();
		} elseif ( $this->showWatchingUsers ) {
			foreach ( $watchingusers as $link ) {
				$out->addWikiTextAsInterface(
					$this->msg( 'whoiswatching-list-user' )->params( $link )
				);
			}
		}
	}
}
