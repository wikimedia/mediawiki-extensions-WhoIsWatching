<?php

class WhoIsWatching extends SpecialPage {

	protected $targetPage = null;
	protected $nameType;
	protected $allowAddingPeople;
	protected $showWatchingUsers;
	protected $showIfZero;

	function __construct() {
		parent::__construct( 'WhoIsWatching' );

		$conf = new GlobalVarConfig( "whoiswatching_" );
		$user = $this->getUser();
		$this->nameType = $conf->get( "nametype" );
		$this->allowAddingPeople = ( !$user->isAnon() && $conf->get( "allowaddingpeople" ) ) ||
			$user->isAllowed( "addpagetoanywatchlist" );
		$this->showWatchingUsers = $conf->get( "showwatchingusers" ) ||
			$user->isAllowed( "seepagewatchers" );

		return true;
	}

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

	public function checkPermissions() {
		if ( $this->showWatchingUsers || $this->allowAddingPeople ) {
			return true;
		}
		throw new ErrorPageError(
			"whoiswatching-permission-denied-title", "whoiswatching-permission-denied",
			[ $this->getLanguage()->commaList( [ "seepagewatchers", "addpagetoanywatchlist" ] ) ]
		);
	}

	protected function getTargetPage( $par ) {
		$title = $this->getRequest()->getVal( 'page' );
		if ( !$title && !$par ) {
			return $this->pickPage();
		}

		if ( $title ) {
			$nsRevLookup = array_flip( MWNamespace::getCanonicalNamespaces() );
			$ns = $this->getRequest()->getVal( 'ns', '' );
			if ( !ctype_digit( $ns ) ) {
				$ns = isset( $nsRevLookup[ $ns ] ) ? $nsRevLookup[ $ns ] : null;
			}
			$this->targetPage = Title::newFromText( $title, $ns );
		} else {
			$this->targetPage = Title::newFromText( $par );
		}

		if ( !$this->targetPage ) {
			throw new ErrorPageError( "whoiswatching-usage-title", "specialwhoiswatchingusage" );
		}
		$ns = $this->targetPage->getNamespace();
		if ( $ns < 0 ) {
			throw new ErrorPageError(
				"whoiswatching-not-possible-title", "whoiswatching-not-possible", [ $this->targetPage ]
			);
		}

		return true;
	}

	protected function addWatchersForm() {
		if ( $this->allowAddingPeople === false ) {
			return false;
		}

		$this->getOutput()->addModules( 'mediawiki.userSuggest' );

		$this->getOutput()->addHTML(
			Html::openElement(
				'form',
				[ 'method' => 'post',
				  'action' => $this->getPageTitle( $this->targetPage )->getLocalUrl(),
				  'name' => 'uluser',
				  'id' => 'mw-whoiswatching-form1' ]
			) .
			Html::hidden( 'addToken', $this->getUser()->getEditToken( __CLASS__ ) ) .
            ( !$this->targetPage->exists() ? '<b>This page does not (yet) exist!</b>' : '' ) .
			Xml::fieldset( $this->msg( 'whoiswatching-lookup-user' )->text() ) .
			Xml::inputLabel(
				$this->msg( 'whoiswatching-user-editname' )->text(),
				'user',
				'username',
				30,
				'',
				[ 'autofocus' => true,
				  'class' => 'mw-autocomplete-user' ] // used by mediawiki.userSuggest
			) . ' ' .
			Xml::submitButton( $this->msg( 'whoiswatching-adduser' )->text() ) .
			Html::closeElement( 'fieldset' ) .
			Html::closeElement( 'form' ) . "\n"
		);

		if ( $this->maybeAddWatcher() ) {
			$this->uiNotifyUser();
		}
		return false;
	}

	protected function maybeAddWatcher() {
		$req = $this->getRequest();
		$token = $req->getVal( 'addToken' );
		if ( $req->wasPosted() && $token ) {
			if ( $this->getUser()->matchEditToken( $token, __CLASS__ ) ) {
				$user = User::newFromName( $req->getVal( 'user' ) );
				$title = $this->targetPage;
				$user->addWatch( $title );
				$this->getOutput()->redirect( $this->getPageTitle( $title )->getLocalUrl() );
                $this->eNotifUser( 'add', $title, $user );
				return true;
			}

			throw new ErrorPageError( 'sessionfailure-title', 'sessionfailure' );
		}
		return true;
	}

	protected function eNotifUser( $action, Title $title, User $user ) {
	}

	protected function uiNotifyUser( ) {
	}

	protected function pickPage() {
		$target = $this->getRequest()->getVal( "target" );
		if ( $target ) {
			$this->getOutput()->redirect( $this->getPageTitle( $target )->getLocalUrl() );
			return false;
		}
		$this->getOutput()->addHTML(
			Html::openElement(
				'form',
				[ 'method' => 'get',
				  'action' => $this->getPageTitle( $target )->getLocalUrl(),
				  'name' => 'uluser',
				  'id' => 'mw-whoiswatching-form1' ] ) .
			Html::hidden( 'title', $this->getPageTitle()->getPrefixedText() ) .
			Xml::fieldset( $this->msg( 'whoiswatching-lookup-title' )->text() ) .
			Xml::inputLabel( $this->msg( 'whoiswatching-title' )->text(), 'target',
							 'whoiswatching-target', 40,
							 str_replace( '_', ' ', $this->targetPage ),
							 [ 'class' => 'mw-searchInput' ] ) . ' ' .
			Xml::submitButton( $this->msg( 'whoiswatching-select-title' )->text() ) .
			Html::closeElement( 'fieldset' ) .
			Html::closeElement( 'form' ) . "\n"
		);
		return false;
	}

	public function maybeRemoveWatcher( array $formData ) {
		foreach ( $formData as $watcherID => $remove ) {
			if ( $remove ) {
				$watcher = User::newFromId( $watcherID );
				$watcher->removeWatch( $this->targetPage );
				$this->eNotifUser( 'remove', $this->targetPage, $watcher );
			}
		}
	}

	protected function showWatchingUsers() {
		if ( $this->showWatchingUsers === false ) {
			return;
		}

		$out = $this->getOutput();
		$out->addWikiText(
			"== ". wfMessage( 'specialwhoiswatchingpage' )->params( $this->targetPage )->plain() . " =="
		);

		$dbr = wfGetDB( DB_SLAVE );
		$watchingusers = [];
		$res = $dbr->select(
			'watchlist', 'wl_user', [ 'wl_namespace' => $this->targetPage->getNamespace(),
									  'wl_title' => $this->targetPage->getDBkey() ], __METHOD__ );
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
			$form = new HTMLForm( $users, $this->getContext() );
			$form->setSubmitText( $this->msg( 'whoiswatching-deluser' )->text() );
			$form->setSubmitCallback( [ $this, 'maybeRemoveWatcher' ] );
			$form->show();
		}
	}

	public static function onSkinTemplateOutputPageBeforeExec( Skin $template, QuickTemplate $tpl ) {
		$conf = new GlobalVarConfig( "whoiswatching_" );
		$showIfZero = $conf->get( "showifzero" );
		$showWatchingUsers = $conf->get( "showwatchingusers" );

		if (
			RequestContext::getMain()->getOutput()->getTitle()->getNamespace() >= 0 &&
			$showWatchingUsers
		) {
			$dbr = wfGetDB( DB_SLAVE );
			$title = $template->getTitle();
			$res = $dbr->select( 'watchlist', 'COUNT(*) as count', [
								 'wl_namespace' => $title->getNamespace(),
								 'wl_title' => $title->getDBkey(),
			], __METHOD__ );
			$watch = $dbr->fetchObject( $res );
			if ( $watch->count > 0 || $showIfZero ) {
				$msg = wfMessage( 'whoiswatching_users_pageview',
					RequestContext::getMain()->getLanguage()->formatNum( $watch->count )
				)->parse();
				$tpl->set( 'numberofwatchingusers', $msg );
			}
		}

		return true;
	}
}
