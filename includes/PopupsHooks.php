<?php
/*
 * This file is part of the MediaWiki extension Popups.
 *
 * Popups is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * Popups is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Popups.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup extensions
 */
namespace Popups;

use MediaWiki\Cache\HTMLCacheUpdater;
use MediaWiki\Cache\Hook\HtmlCacheUpdaterAppendUrlsHook;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\Hook\MakeGlobalVariablesScriptHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use Psr\Log\LoggerInterface;
use Skin;

/**
 * Hooks definitions for Popups extension
 *
 * @package Popups
 */
class PopupsHooks implements
	GetPreferencesHook,
	BeforePageDisplayHook,
	HtmlCacheUpdaterAppendUrlsHook,
	ResourceLoaderGetConfigVarsHook,
	MakeGlobalVariablesScriptHook
{

	private const PREVIEWS_PREFERENCES_SECTION = 'rendering/reading';

	/** @var Config */
	private $config;

	/** @var PopupsContext */
	private $popupsContext;

	/** @var LoggerInterface */
	private $logger;

	/** @var UserOptionsManager */
	private $userOptionsManager;

	/**
	 * @param Config $config
	 * @param PopupsContext $popupsContext
	 * @param LoggerInterface $logger
	 * @param UserOptionsManager $userOptionsManager
	 */
	public function __construct(
		Config $config,
		PopupsContext $popupsContext,
		LoggerInterface $logger,
		UserOptionsManager $userOptionsManager
	) {
		$this->config = $config;
		$this->popupsContext = $popupsContext;
		$this->logger = $logger;
		$this->userOptionsManager = $userOptionsManager;
	}

	/**
	 * Get custom Popups types registered by extensions
	 * @return array
	 */
	public static function getCustomPopupTypes(): array {
		return ExtensionRegistry::getInstance()->getAttribute(
			'PopupsPluginModules'
		);
	}

	/**
	 * Add options to user Preferences page
	 *
	 * @param User $user User whose preferences are being modified
	 * @param array[] &$prefs Preferences description array, to be fed to a HTMLForm object
	 */
	public function onGetPreferences( $user, &$prefs ) {
		if ( !$this->popupsContext->showPreviewsOptInOnPreferencesPage() ) {
			return;
		}

		$skinPosition = array_search( 'skin', array_keys( $prefs ) );
		$readingOptions = $this->getPagePreviewPrefToggle( $user );

		if ( $skinPosition !== false ) {
			$injectIntoIndex = $skinPosition + 1;
			$prefs = array_slice( $prefs, 0, $injectIntoIndex, true )
				+ $readingOptions
				+ array_slice( $prefs, $injectIntoIndex, null, true );
		} else {
			$prefs += $readingOptions;
		}
	}

	/**
	 * Get Page Preview option
	 *
	 * @param User $user User whose preferences are being modified
	 * @return array[]
	 */
	private function getPagePreviewPrefToggle( User $user ) {
		$option = [
			'type' => 'toggle',
			'label-message' => 'popups-prefs-optin',
			'help-message' => 'popups-prefs-conflicting-gadgets-info',
			'section' => self::PREVIEWS_PREFERENCES_SECTION
		];

		if ( $this->popupsContext->conflictsWithNavPopupsGadget( $user ) ) {
			$option[ 'disabled' ] = true;
			$option[ 'help-message' ] = [ 'popups-prefs-disable-nav-gadgets-info',
				'Special:Preferences#mw-prefsection-gadgets' ];
		}

		return [
			'popups' => $option
		];
	}

	/**
	 * Allows last minute changes to the output page, e.g. adding of CSS or JavaScript by extensions.
	 *
	 * @param OutputPage $out The Output page object
	 * @param Skin $skin Skin object that will be used to generate the page
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( $this->popupsContext->isTitleExcluded( $out->getTitle() ) ) {
			return;
		}

		if ( !$this->popupsContext->areDependenciesMet() ) {
			$this->logger->error( 'Popups requires the PageImages extensions.
				TextExtracts extension is required when using mwApiPlain gateway.' );
			return;
		}

		$out->addModules( [ 'ext.popups' ] );
	}

	/**
	 * Hook handler for the ResourceLoaderStartUpModule that makes static configuration visible to
	 * the frontend. These variables end in the only "startup" ResourceLoader module that is loaded
	 * before all others.
	 *
	 * Dynamic configuration that depends on the context needs to be published via the
	 * MakeGlobalVariablesScript hook.
	 *
	 * @param array &$vars Array of variables to be added into the output of the startup module
	 * @param string $skin
	 * @param Config $config
	 */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		$vars['wgPopupsVirtualPageViews'] = $this->config->get( 'PopupsVirtualPageViews' );
		$vars['wgPopupsGateway'] = $this->config->get( 'PopupsGateway' );
		$vars['wgPopupsRestGatewayEndpoint'] = $this->config->get( 'PopupsRestGatewayEndpoint' );
		$vars['wgPopupsStatsvSamplingRate'] = $this->config->get( 'PopupsStatsvSamplingRate' );
		$vars['wgPopupsTextExtractsIntroOnly'] = $this->config->get( 'PopupsTextExtractsIntroOnly' );
	}

	/**
	 * Hook handler publishing dynamic configuration that depends on the context, e.g. the page or
	 * the users settings. These variables end in an inline <script> in the documents head.
	 *
	 * Variables added:
	 * * `wgPopupsConflictsWithNavPopupGadget' - The server's notion of whether or not the
	 *   user has enabled conflicting Navigational Popups Gadget.
	 * * `wgPopupsConflictsWithRefTooltipsGadget' - The server's notion of whether or not the
	 *   user has enabled conflicting Reference Tooltips Gadget.
	 *
	 * @param array &$vars variables to be added into the output of OutputPage::headElement
	 * @param IContextSource $out OutputPage instance calling the hook
	 */
	public function onMakeGlobalVariablesScript( &$vars, $out ): void {
		$vars['wgPopupsFlags'] = $this->popupsContext->getConfigBitmaskFromUser( $out->getUser() );
	}

	/**
	 * Build the API URL used in the src/gateway/mediawiki.js call
	 * @param bool $exIntro
	 * @param int $thumbSize
	 * @param string $page
	 * @return string
	 */
	private function buildApiUrl( string $exIntro, int $thumbSize, string $page): string {
		$apiUrl = (string)MediaWikiServices::getInstance()->getUrlUtils()->expand( wfScript( 'api' ) );
		return $apiUrl . "?action=query&format=json&prop=info%7Cextracts%7Cpageimages%7Crevisions%7Cinfo&formatversion=2&redirects=true&exintro=$exIntro&exchars=525&explaintext=true&exsectionformat=plain&piprop=thumbnail&pithumbsize=$thumbSize&pilicense=any&rvprop=timestamp&inprop=url&titles=$page&smaxage=300&maxage=300&uselang=content";
	}

	/**
	 * Purge the API URLs we're requesting when the page changes.
	 *
	 * @param Title $title
	 * @param int $mode
	 * @param array[] $append
	 */
	public function onHtmlCacheUpdaterAppendUrls( $title, $mode, &$append ) {
		// Do not run for links updates, as it would create a lot of unnecessary purges
		if ( $mode === HTMLCacheUpdater::PURGE_URLS_LINKSUPDATE_ONLY ) {
			return null;
		}

		// Do not run for any Title that the extension is not running on
		/** @var PopupsContext $context */
		$context = MediaWikiServices::getInstance()->getService( 'Popups.Context' );
		if ( $context->isTitleExcluded( $title ) ) {
			return null;
		}

		/** @var Config $config */
		$config = MediaWikiServices::getInstance()->getService( 'Popups.Config' );

		// Check we're using the PageExtracts API
		if ( $config->get( 'PopupsGateway' ) === 'mwApiPlain' ) {
			$exintro = $config->get( 'PopupsTextExtractsIntroOnly' ) ? 'true' : 'false';
			$page = $title->getPrefixedDBkey();

			// Append all possible extract API URLs, based on values in src/bracketedPixelRatio.js
			$append[] = self::buildApiUrl( $exintro, 480, $page );
			$append[] = self::buildApiUrl( $exintro, 640, $page );
		}
	}
}
