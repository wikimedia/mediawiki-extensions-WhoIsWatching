<?php

class WhoIsWatching extends SpecialPage {

	function __construct() {
		parent::__construct( 'WhoIsWatching' );
		return true;
	}

	function execute( $par ) {
		global $wgRequest, $wgOut, $wgUser;
		global $whoiswatching_nametype, $whoiswatching_allowaddingpeople;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'whoiswatching' ) );

		$title = $wgRequest->getVal( 'page' );
		$ns = $wgRequest->getVal( 'ns', '' );
		if ( $ns !== '' ) {
			$title = $ns.':'.$title;
		}
		$pageTitle = Title::newFromText( $title );
		if ( !$title || !$pageTitle ) {
			$wgOut->addWikiMsg( 'specialwhoiswatchingusage' );
			return;
		}

		if ( $whoiswatching_allowaddingpeople &&
			$wgRequest->wasPosted() &&
			$wgUser->matchEditToken( $wgRequest->getVal( 'token' ) ) ) {
			$idArray = $wgRequest->getArray( 'idArray' );
			foreach ( $idArray as $name => $id ) {
				#$wgOut->addWikiText("* Adding name $name userid $id to watchlist\n");
				$u = User::newFromId( $id );
				$u->addWatch( $pageTitle );
			}
			$wgOut->redirect( Title::makeTitle( NS_SPECIAL, 'WhoIsWatching' )->getLocalUrl(
				array( 'page' => $title )
			) );
			return;
		}

		$wgOut->addWikiText( "== ".sprintf( wfMsg( 'specialwhoiswatchingthepage' ), "[[:$pageTitle]] ==" ) );

		$dbr = wfGetDB( DB_SLAVE );
		$watchingusers = array();
		$res = $dbr->select(
			'watchlist', 'wl_user', array(
				'wl_namespace' => $pageTitle->getNamespace(),
				'wl_title' => $pageTitle->getDBkey(),
			), __METHOD__ );
		foreach ( $res as $row ) {
			$u = User::newFromID( $row->wl_user );
			if ( ( $whoiswatching_nametype == 'UserName' ) || !$u->getRealName() ) {
				$watchingusers[$row->wl_user] = ":[[User:" . $u->getName() . "]]";
			} else {
				$watchingusers[$row->wl_user] = ":[[:User:" . $u->getName() . "|" . $u->getRealName() . "]]";
			}
		}

		asort( $watchingusers );
		$out = '';
		foreach ( $watchingusers as $id => $link ) {
			$out .= "$link\n";
		}
		$wgOut->addWikiText( $out );

		if ( $whoiswatching_allowaddingpeople ) {
			$wgOut->addWikiText( "== ".wfMsg( 'specialwhoiswatchingaddusers')." ==" );
			$wgOut->addHTML( "<form method=\"post\">" );
			$wgOut->addHTML( "<input type=\"hidden\" value=\"".$wgUser->getEditToken()."\" name=\"token\" />" );
			$wgOut->addHTML( "<div style=\"border: thin solid #000000\"><table cellpadding=\"15\" cellspacing=\"0\" border=\"0\">" );
			$wgOut->addHTML( "<tr><td>" );
			$wgOut->addHTML( '<select name="idArray[]" size="12" multiple="multiple">' );
			$users = array();
			$res = $dbr->select( 'user', 'user_name', '', __METHOD__);
			foreach ( $res as $row ) {
				$u = User::newFromName( $row->user_name );
				if ( !array_key_exists( $u->getID(), $watchingusers ) &&
					$u->isAllowed( 'read' ) && $u->getEmail() ) {
					$users[ $u->getID() ] = $u->getRealName() ? $u->getRealName() : $u->getName();
				}
			}
			asort( $users );
			foreach ( $users as $id => $name ) {
				$wgOut->addHTML( "<option value=\"".$id."\">".$name."</option>" );
			}
			$wgOut->addHTML( '</select></td><td>' );
			$wgOut->addHTML( "<input type=\"submit\" value=\"".wfMsg( 'specialwhoiswatchingaddbtn' )."\" />" );
			$wgOut->addHTML( "</td></tr></table></div></form>" );
		}
	}
}
