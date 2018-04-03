<?php
/**
 * Special page for WhoIsWatching
 *
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

use ErrorPageError;
use GlobalVarConfig;
use HTML;
use HTMLForm;
use MWNamespace;
use SpecialPage;
use Title;
use User;
use XML;

class SpecialWhoIsWatching extends SpecialPage {

	protected $targetPage = null;
	protected $nameType;
	protected $allowAddingPeople;
	protected $showWatchingUsers;
	protected $showIfZero;

	/**
	 * Ye olde constructor
	 * @return boolean
	 */
	function __construct() {
		parent::__construct( 'whoiswatching' );

		$conf = new GlobalVarConfig( "whoiswatching_" );
		$user = $this->getUser();
		$this->nameType = $conf->get( "nametype" );
		$this->allowAddingPeople = ( !$user->isAnon()
									 && $conf->get( "allowaddingpeople" ) )
								 || $user->isAllowed( "addpagetoanywatchlist" );
		$this->showWatchingUsers = $conf->get( "showwatchingusers" ) ||
			$user->isAllowed( "seepagewatchers" );

		return true;
	}

	/**
	 * Page executioner
	 * @param string $par page we're messing with
	 * @return boolean
	 */
	function execute( $par ) {
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
	 * @return void
	 * @throws ErrorPageError
	 */
	public function checkPermissions() {
		if ( $this->showWatchingUsers || $this->allowAddingPeople ) {
			return parent::checkPermissions();
		}
		throw new ErrorPageError(
			"whoiswatching-permission-denied-title",
			"whoiswatching-permission-denied",
			[ $this->getLanguage()->commaList(
				[ "seepagewatchers", "addpagetoanywatchlist" ]
			) ]
		);
	}

	/**
	 * Either return the page chosen or display a page chooser and return false
	 * @param string $par the passed in parameter
	 * @return boolean|Title
	 * @throws ErrorPageError
	 */
	protected function getTargetPage( $par ) {
		$title = $this->getRequest()->getVal( 'page' );
		if ( !$title && !$par ) {
			return $this->pickPage();
		}

		if ( $title ) {
			$nsRevLookup = array_flip( MWNamespace::getCanonicalNamespaces() );
			$nameSpace = $this->getRequest()->getVal( 'ns', '' );
			if ( !ctype_digit( $nameSpace ) ) {
				$nameSpace = isset( $nsRevLookup[ $nameSpace ] )
						   ? $nsRevLookup[ $nameSpace ]
						   : null;
			}
			$this->targetPage = Title::newFromText( $title, $nameSpace );
		} else {
			$this->targetPage = Title::newFromText( $par );
		}

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
	 * @return boolean
	 */
	protected function addWatchersForm() {
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
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
			->addHiddenField( 'addToken', $this->getUser()->getEditToken( __CLASS__ ) )
			->setAction( $this->getPageTitle( $this->targetPage )->getLocalUrl() )
			->setId('mw-whoiswatching-form1')
			->setMethod( 'post' )
			->setName( 'uluser' )
			->setSubmitTextMsg( 'whoiswatching-adduser' )
			->setWrapperLegendMsg( 'whoiswatching-lookup-user' )
			->prepareForm()
			->displayForm( false );

		$this->maybeAddWatcher();
		return false;
	}

	/**
	 * Add a watcher if the request did so
	 * @return boolean
	 * @throws ErrorPageError
	 */
	protected function maybeAddWatcher() {
		$req = $this->getRequest();
		$token = $req->getVal( 'addToken' );
		if ( $req->wasPosted() && $token ) {
			if ( $this->getUser()->matchEditToken( $token, __CLASS__ ) ) {
				$user = User::newFromName( $req->getVal( 'user' ) );
				$title = $this->targetPage;
				$user->addWatch( $title );
				$this->getOutput()->redirect
					( $this->getPageTitle( $title )->getLocalUrl() );
				$this->eNotifUser( 'add', $title, $user );
				return true;
			}

			throw new ErrorPageError
				( 'sessionfailure-title', 'sessionfailure' );
		}
		return true;
	}

	/**
	 * FIXME needs to be fleshed out
	 * @param string $action taked
	 * @param Title $title page updated
	 * @param User $user affected
	 */
	protected function eNotifUser( $action, Title $title, User $user ) {
	}

	/**
	 * If the user selected a page, redirect to work on it.  If not,
	 * show the form.
	 * @return boolean
	 */
	protected function pickPage() {
		$target = $this->getRequest()->getVal( "target" );
		if ( $target ) {
			$this->getOutput()->redirect
				( $this->getPageTitle( $target )->getLocalUrl() );
			return false;
		}
		$formDescriptor = [
			'page' => [
				'type' => 'text',
				'name' => 'target',
				'label-message' => 'whoiswatching-title',
				'size' => 40,
				'id' => 'whoiswatching-target',
				'value' => str_replace( '_', ' ', $this->targetPage ) ,
				'cssclass' => 'mw-searchInput'
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
			->addHiddenField( 'title', $this->getPageTitle()->getPrefixedText() )
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
	 * @param array $formData posted data
	 * @param HTMLForm $form the whole form
	 */
	protected function maybeRemoveWatcher( array $formData, HTMLForm $form ) {
		foreach ( $formData as $watcherID => $remove ) {
			if ( $remove ) {
				$watcher = User::newFromId( $watcherID );
				$watcher->removeWatch( $this->targetPage );
				# We should somehow remove this field from the form,
				# but it looks too late now.
				$field = $form->getField( $watcherID );
				$field->mParams['disabled'] = true;
				$field->setShowEmptyLabel( false );
				$this->getOutput()->addModules( "ext.whoIsWatching" );
				$this->eNotifUser( 'remove', $this->targetPage, $watcher );
			}
		}
	}

	/**
	 * Show watching users if we can
	 * @return null
	 */
	protected function showWatchingUsers() {
		if ( $this->showWatchingUsers === false ) {
			return;
		}

		$out = $this->getOutput();
		$out->addWikiText(
			"== ". wfMessage( 'specialwhoiswatchingpage' )
			->params( $this->targetPage )->plain() . " =="
		);

		$dbr = wfGetDB( DB_SLAVE );
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
		if ( $this->allowAddingPeople ) {
			$form = HTMLForm::factory( 'ooui', $users, $this->getContext() );
			$form->setSubmitText
				( $this->msg( 'whoiswatching-deluser' )->text() );
			$form->setSubmitCallback(
				function ( $formData, $form ) {
					return $this->maybeRemoveWatcher( $formData, $form );
				} );
			$form->setSubmitDestructive();
			$form->show();
		}
	}
}
