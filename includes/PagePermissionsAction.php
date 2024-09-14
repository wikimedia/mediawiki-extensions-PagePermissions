<?php

use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\User\UserFactory;
use Wikimedia\Rdbms\ReadOnlyMode;

class PagePermissionsAction extends Action {

	private PermissionManager $permissionManager;
	private ReadOnlyMode $readOnlyMode;
	private RestrictionStore $restrictionStore;
	private UserFactory $userFactory;

	public function __construct(
		Article $page,
		IContextSource $context,
		PermissionManager $permissionManager,
		ReadOnlyMode $readOnlyMode,
		RestrictionStore $restrictionStore,
		UserFactory $userFactory
	) {
		parent::__construct( $page, $context );
		$this->permissionManager = $permissionManager;
		$this->readOnlyMode = $readOnlyMode;
		$this->restrictionStore = $restrictionStore;
		$this->userFactory = $userFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function getName() {
		return 'pagepermissions';
	}

	/**
	 * @inheritDoc
	 */
	public function show() {
		$permissions = RequestContext::getMain()->getConfig()->get( 'PagePermissionsRoles' );
		$roles = array_keys( $permissions );
		$form = new PagePermissionsForm(
			$this,
			$roles,
			$this->permissionManager,
			$this->readOnlyMode,
			$this->restrictionStore,
			$this->userFactory
		);
		$form->execute();
	}

	/**
	 * @inheritDoc
	 */
	public function doesWrites() {
		return true;
	}
}
