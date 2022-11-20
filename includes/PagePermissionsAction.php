<?php

class PagePermissionsAction extends Action {

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
		$form = new PagePermissionsForm( $this, $roles );
		$form->execute();
	}

	/**
	 * @inheritDoc
	 */
	public function doesWrites() {
		return true;
	}
}
