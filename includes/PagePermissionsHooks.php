<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;

class PagePermissionsHooks {

	/**
	 * Alter the structured navigation links in SkinTemplates
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation::Universal
	 * @param SkinTemplate $skinTemplate
	 * @param array &$links
	 */
	public static function onSkinTemplateNavigationUniversal( SkinTemplate $skinTemplate, array &$links ) {
		$title = $skinTemplate->getTitle();
		if ( !$title ) {
			return;
		}

		$services = MediaWikiServices::getInstance();
		$permissionManager = $services->getPermissionManager();
		$user = $skinTemplate->getUser();
		$ns = $title->getNamespace();
		if ( !( $permissionManager->quickUserCan( 'pagepermissions', $user, $title ) &&
			$services->getRestrictionStore()->listApplicableRestrictionTypes( $title ) &&
			$permissionManager->getNamespaceRestrictionLevels( $ns, $user ) !== [ '' ] )
		) {
			return;
		}

		$action = $skinTemplate->getRequest()->getVal( 'action', 'view' );
		$class = 'pagepermissions-menu-item';
		if ( $action === 'pagepermissions' ) {
			$class .= ' selected';
		}

		$links['actions']['pagepermissions'] = [
			'class' => $class,
			'text' => $skinTemplate->msg( 'pagepermissions-tab-text' )->text(),
			'href' => $title->getLocalUrl( [ 'action' => 'pagepermissions' ] )
		];
	}

	/**
	 * Register UserProtect services
	 *
	 * @param MediaWikiServices $container
	 */
	public static function onMediaWikiServices( MediaWikiServices $container ) {
		$container->redefineService(
			'PermissionManager',
			static function ( MediaWikiServices $services ): PermissionManager {
				return new PagePermissionsManager(
					new ServiceOptions(
						PermissionManager::CONSTRUCTOR_OPTIONS, $services->getMainConfig()
					),
					$services->getSpecialPageFactory(),
					$services->getNamespaceInfo(),
					$services->getGroupPermissionsLookup(),
					$services->getUserGroupManager(),
					$services->getBlockErrorFormatter(),
					$services->getHookContainer(),
					$services->getUserCache(),
					$services->getRedirectLookup(),
					$services->getRestrictionStore(),
					$services->getTitleFormatter(),
					$services->getTempUserConfig(),
					$services->getUserFactory(),
					$services->getActionFactory()
				);
			}
		);
	}

	/**
	 * Occurs after the delete article request has been processed
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ArticleDeleteComplete
	 * @param MediaWiki\Page\ProperPageIdentity $page
	 * @param MediaWiki\Permissions\Authority $deleter
	 * @param string $reason
	 * @param int $pageID
	 * @param MediaWiki\Revision\RevisionRecord $deletedRev
	 * @param ManualLogEntry $logEntry
	 * @param int $archivedRevisionCount
	 */
	public static function onPageDeleteComplete(
		MediaWiki\Page\ProperPageIdentity $page,
		MediaWiki\Permissions\Authority $deleter, string $reason,
		int $pageID, MediaWiki\Revision\RevisionRecord $deletedRev,
		ManualLogEntry $logEntry, int $archivedRevisionCount
	) {
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->delete(
			'pagepermissions',
			[
				'pper_page_id' => $pageID,
			],
			__METHOD__
		);
	}

	/**
	 * This is attached to the MediaWiki 'LoadExtensionSchemaUpdates' hook.
	 * Fired when MediaWiki is updated to allow extensions to update the database.
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( 'pagepermissions', __DIR__ . '/../db_patches/pagepermissions.sql' );
	}
}
