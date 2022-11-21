<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;

class PagePermissionsForm {

	/** @var array Permissions errors for the protect action */
	private $permErrors = [];

	/** @var Title */
	private $title;

	/** @var bool */
	private $disabled;

	/** @var array */
	private $disabledAttrib;

	/** @var array */
	private $rights;

	/** @var array */
	private $roles;

	/** @var Action */
	private $action;

	/** @var PermissionManager */
	private $permManager;

	/** @var IContextSource */
	private $context;

	/**
	 * PagePermissionsForm constructor.
	 *
	 * @param Action $action
	 * @param array $roles
	 */
	public function __construct( Action $action, array $roles ) {
		// Set instance variables.
		$this->action = $action;
		$this->title = $action->getTitle();
		$this->context = $action->getContext();

		// Check if the form should be disabled.
		// If it is, the form will be available in read-only to show levels.
		$services = MediaWikiServices::getInstance();
		$this->permManager = $services->getPermissionManager();
		$rigor = $this->context->getRequest()->wasPosted()
			? PermissionManager::RIGOR_SECURE
			: PermissionManager::RIGOR_FULL;
		$this->permErrors = $this->permManager->getPermissionErrors(
			'pagepermissions',
			$action->getUser(),
			$this->title,
			$rigor
		);
		$readOnlyMode = $services->getReadOnlyMode();
		if ( $readOnlyMode->isReadOnly() ) {
			$this->permErrors[] = [ 'readonlytext', $readOnlyMode->getReason() ];
		}
		$this->disabled = $this->permErrors !== [];
		$this->disabledAttrib = $this->disabled
			? [ 'disabled' => 'disabled' ]
			: [];

		$text = '';

		$this->roles = $roles;

		foreach ( $this->roles as $role ) {
			$this->rights[ $role ] = [];
		}

		$this->loadData();
	}

	/**
	 * Main entry point for action=pagepermissions
	 *
	 * @throws ErrorPageError
	 * @throws MWException
	 */
	public function execute() {
		if ( $this->permManager->getNamespaceRestrictionLevels(
				$this->title->getNamespace()
			) === [ '' ]
		) {
			throw new ErrorPageError( 'protect-badnamespace-title', 'protect-badnamespace-text' );
		}

		if ( $this->context->getRequest()->wasPosted() ) {
			if ( !$this->save() ) {
				// $this->show() called already
				return;
			}
			// Reload data from the database
			foreach ( $this->roles as $role ) {
				$this->rights[ $role ] = [];
			}
			$this->loadData();
		}
		$this->show();
	}

	/**
	 * Loads the current state of protection into the object
	 */
	private function loadData() {
		$pageId = $this->title->getArticleID();

		$table = 'pagepermissions';
		$vars[ 'user' ] = 'pper_user_id';
		$vars['type'] = 'pper_permission';

		$dbr = wfGetDB( DB_REPLICA );

		foreach ( $this->roles as $role ) {
			$conds = [
				'pper_page_id' => $pageId,
				'pper_permission' => $role
			];
			$res = $dbr->select( $table, $vars, $conds, __METHOD__ );
			foreach ( $res as $row ) {
				$this->rights[ $row->type ][] = User::newFromId( $row->user );
			}
		}
	}

	/**
	 * Save submitted form
	 *
	 * @return bool
	 * @throws MWException
	 */
	private function save(): bool {
		// Permission check
		if ( $this->disabled ) {
			$this->show();
			return false;
		}

		$title = $this->title;
		$request = $this->context->getRequest();
		$contextUser = $this->context->getUser();

		$dbw = wfGetDB( DB_PRIMARY );

		if ( $title->exists() ) {
			$tableName = 'pagepermissions';
			$deleteConds = [ 'pper_page_id' => $title->getArticleID() ];
		}

		$dbw->startAtomic( __METHOD__ );
		$dbw->delete( $tableName, $deleteConds, __METHOD__ );

		$usernames = $users = [];

		foreach ( $this->roles as $role ) {
			$usernames[ $role ] = explode( ',', $request->getVal( $role . '_permission' ) );
			$usernames[ $role ] = array_map( 'trim', $usernames[ $role ] );
			$users[ $role ] = self::getUsersByName( $usernames[ $role ] );
		}

		$rows = [];

		$timestamp = wfTimestampNow();

		foreach ( $this->roles as $role ) {
			if ( $users[ $role ] ) {
				foreach ( $users[ $role ] as $user ) {
					$user = User::newFromId( $user );
					$this->addRowsForType( $users[ $role ], $usernames[ $role ], $role, $timestamp, $rows );
				}
			}
		}

		// @phan-suppress-next-line SecurityCheck-SQLInjection
		$dbw->insert( $tableName, array_unique( $rows, SORT_REGULAR ), __METHOD__ );

		$dbw->endAtomic( __METHOD__ );
		return true;
	}

	/**
	 * Builds row for insertion to the database and adds it to the $rows variable
	 *
	 * @param array $users
	 * @param array $userNames
	 * @param string $type
	 * @param string $timestamp
	 * @param array &$rows
	 */
	private function addRowsForType(
		array $users, array $userNames, string $type,
		string $timestamp, array &$rows
	) {
		$title = $this->title;
		$pageId = $title->getArticleID();
		$dbKey = $title->getDBkey();
		$namespace = $title->getNamespace();

		foreach ( $userNames as $name ) {
			if ( isset( $users[$name] ) ) {
				$userId = $users[$name];
				if ( $pageId ) {
					$rows[] = [
						'pper_page_id' => $pageId,
						'pper_permission' => $type,
						'pper_user_id' => $userId,
						'pper_right_timestamp' => $timestamp,
					];
				}
			}
		}
	}

	/**
	 * Show the input form with optional error message
	 *
	 * @param string|array|null $err
	 * @throws MWException
	 */
	private function show( $err = null ) {
		$title = $this->title;
		$context = $this->context;
		$out = $context->getOutput();
		$out->setRobotPolicy( 'noindex,nofollow' );
		$out->addBacklinkSubtitle( $title );

		if ( is_array( $err ) ) {
			$out->wrapWikiMsg( "<div class='error'>\n$1\n</div>\n", $err );
		} elseif ( is_string( $err ) ) {
			$out->addHTML( "<div class='error'>{$err}</div>\n" );
		}

		if ( MediaWikiServices::getInstance()->getRestrictionStore()
			->listApplicableRestrictionTypes( $title ) === []
		) {
			// No restriction types available for the current title
			// this might happen if an extension alters the available types
			$out->setPageTitle( $context->msg(
				'protect-norestrictiontypes-title',
				$title->getPrefixedText()
			) );
			$out->addWikiTextAsInterface(
				$context->msg( 'protect-norestrictiontypes-text' )->plain()
			);
			return;
		}

		# Show an appropriate message if the user isn't allowed or able to change
		# the protection settings at this time
		if ( $this->disabled ) {
			$out->setPageTitle(
				$context->msg( 'protect-title-notallowed', $title->getPrefixedText() )
			);
			$out->addWikiTextAsInterface(
				$out->formatPermissionsErrorMessage( $this->permErrors, 'pagepermissions' )
			);
		} else {
			$out->setPageTitle( $context->msg( 'protect-title', $title->getPrefixedText() ) );
			$out->addWikiMsg( 'pagepermissions-form-desc', wfEscapeWikiText( $title->getPrefixedText() ) );
		}

		$out->enableOOUI();

		$text = '';

		foreach ( $this->roles as $role ) {
			$text .= new OOUI\FieldLayout(
				new OOUI\MultilineTextInputWidget( [
					'name' => $role . '_permission',
					'value' => implode( ',', $this->rights[ $role ] ),
					'placeholder' => wfMessage( 'pagepermissions-usernames-placeholder' )->text()
				] ),
				[
					'align' => 'top',
					'label' => ucfirst( $role )
				]
			);
		}
		$text .= '<br>';

		$text .= new OOUI\ButtonInputWidget( [
			'type' => 'submit',
			'label' => 'Submit'
		] );

		$form = Html::rawElement( 'form', [ 'id' => 'pagepermissionsform', 'method' => 'post' ], $text );
		$out->addHTML( $form );
	}

	/**
	 * Returns existing user names with user id
	 * as an array [ 'user name' => id ]
	 *
	 * @param array $userNames
	 * @return array
	 */
	private static function getUsersByName( array $userNames ): array {
		if ( !$userNames ) {
			return [];
		}

		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			'user',
			[ 'user_id', 'user_name' ],
			[ 'user_name' => array_unique( $userNames ) ],
			__METHOD__
		);

		$users = [];
		foreach ( $res as $row ) {
			$users[$row->user_name] = (int)$row->user_id;
		}
		return $users;
	}

}
