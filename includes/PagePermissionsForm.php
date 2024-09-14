<?php

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;
use Wikimedia\Rdbms\ReadOnlyMode;

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

	private PermissionManager $permissionManager;
	private RestrictionStore $restrictionStore;
	private UserFactory $userFactory;

	/** @var IContextSource */
	private $context;

	/**
	 * PagePermissionsForm constructor.
	 */
	public function __construct(
		Action $action,
		array $roles,
		PermissionManager $permissionManager,
		ReadOnlyMode $readOnlyMode,
		RestrictionStore $restrictionStore,
		UserFactory $userFactory
	) {
		// Set instance variables.
		$this->action = $action;
		$this->title = $action->getTitle();
		$this->context = $action->getContext();

		// Check if the form should be disabled.
		// If it is, the form will be available in read-only to show levels.
		$this->permissionManager = $permissionManager;
		$this->restrictionStore = $restrictionStore;
		$this->userFactory = $userFactory;
		$rigor = $this->context->getRequest()->wasPosted()
			? PermissionManager::RIGOR_SECURE
			: PermissionManager::RIGOR_FULL;
		$this->permErrors = $this->permissionManager->getPermissionErrors(
			'pagepermissions',
			$action->getUser(),
			$this->title,
			$rigor
		);
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
		if ( $this->permissionManager->getNamespaceRestrictionLevels(
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
		$vars['type'] = 'pper_role';

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		foreach ( $this->roles as $role ) {
			$conds = [
				'pper_page_id' => $pageId,
				'pper_role' => $role
			];
			$res = $dbr->select( $table, $vars, $conds, __METHOD__ );
			foreach ( $res as $row ) {
				$user = $this->userFactory->newFromId( $row->user );
				$this->rights[ $row->type ][] = $user->getName();
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

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		if ( $title->exists() ) {
			$tableName = 'pagepermissions';
			$deleteConds = [ 'pper_page_id' => $title->getArticleID() ];
		}

		$dbw->startAtomic( __METHOD__ );
		$dbw->delete( $tableName, $deleteConds, __METHOD__ );

		$usernames = $users = [];
		foreach ( $this->roles as $role ) {
			$usernames[ $role ] = self::getUserNames( $request, $role . '_permission' );
			$users[ $role ] = self::getUsersByName( $usernames[ $role ] );
		}

		$rows = [];

		$timestamp = wfTimestampNow();

		foreach ( $this->roles as $role ) {
			if ( $users[ $role ] ) {
				foreach ( $users[ $role ] as $user ) {
					$user = $this->userFactory->newFromId( $user );
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
						'pper_role' => $type,
						'pper_user_id' => $userId,
						'pper_timestamp' => $timestamp,
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

		if ( $context->getRequest()->wasPosted() ) {
			$successMsg = $context->msg( 'pagepermissions-saved' )->escaped();
			$out->addHTML( Html::successBox( $successMsg ) );
		}

		if ( is_array( $err ) ) {
			$out->wrapWikiMsg( "<div class='error'>\n$1\n</div>\n", $err );
		} elseif ( is_string( $err ) ) {
			$out->addHTML( "<div class='error'>{$err}</div>\n" );
		}

		if ( $this->restrictionStore->listApplicableRestrictionTypes( $title ) === [] ) {
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
		$config = [];
		# Show an appropriate message if the user isn't allowed or able to change
		# the protection settings at this time
		if ( $this->disabled ) {
			$out->setPageTitle(
				$context->msg( 'pagepermissions-not-allowed', $title->getPrefixedText() )
			);
			$out->addWikiTextAsInterface(
				$out->formatPermissionsErrorMessage( $this->permErrors, 'pagepermissions' )
			);
			$out->addJsConfigVars( [
				'permissionsError' => $this->permErrors
			] );
			$config[ 'permissionsError' ] = $this->permErrors;
		} else {
			$out->setPageTitle( $context->msg( 'pagepermissions-title', $title->getPrefixedText() ) );
			$out->addWikiMsg( 'pagepermissions-form-desc', wfEscapeWikiText( $title->getPrefixedText() ) );
			$config[ 'permissionsConfig' ] = [
				'roles' => $this->roles,
				'rights' => $this->rights
			];
		}

		$allUsernames = self::getAllUserNames();

		$out->addJsConfigVars( $config );
		$out->addModules( 'ext.pagepermissions.form' );
		$out->enableOOUI();
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

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
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

	/**
	 * Returns submitted user names from UsersMultiselectWidget
	 *
	 * @param WebRequest $request
	 * @param string $name
	 * @return array
	 */
	private static function getUserNames( WebRequest $request, string $name ): array {
		$value = $request->getVal( $name );
		return $value ? explode( "\r\n", $value ) : [];
	}

	/**
	 * Returns a list of all usernames except the existing usernames
	 *
	 * @return array
	 */
	private static function getAllUserNames() {
		$usernames = [];
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$res = $dbr->select( 'user', 'user_name' );
		foreach ( $res as $row ) {
			$usernames[] = $row->user_name;
		}
		// Remove Mediawiki default and maintenance script from usernames list
		$usernames = array_slice( $usernames, 2 );
		return $usernames;
	}

}
