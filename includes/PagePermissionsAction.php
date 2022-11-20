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
		$restrictions = RequestContext::getMain()->getConfig()->get( 'PagePermissionsRestrictionTypes' );
		$roles = array_keys( $restrictions );
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
