<?php

namespace MediaWiki\Skins\Femiwiki;

use Html;
use Linker;
use MediaWiki\MediaWikiServices;
use OOUI\ButtonWidget;
use OutputPage;
use Sanitizer;
use SkinMustache;
use SpecialPage;

/**
 * Skin subclass for Femiwiki
 *
 * @ingroup Skins
 * @internal
 */
class SkinFemiwiki extends SkinMustache {

	/** @var array|mixed */
	private $xeIconMap;
	/** @var bool */
	private $isREL1_39 = false;

	/** @inheritDoc */
	public function __construct( $options ) {
		$this->isREL1_39 = version_compare( MW_VERSION, '1.38', '>' );
		parent::__construct( $options );
	}

	/**
	 * @inheritDoc
	 */
	public function getDefaultModules() {
		$user = $this->getUser();
		$registered = $user->isRegistered();
		$config = $this->getConfig();
		$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();

		if ( $registered ) {
			$this->options['scripts'][] = 'skins.femiwiki.notifications';
		}
		if ( $this->shouldShowShare() ) {
			$this->options['scripts'][] = 'skins.femiwiki.share';
		}
		if (
			( !$registered && $config->get( Constants::CONFIG_KEY_SMALL_ELEMENTS_FOR_ANONYMOUS_USER ) )
			|| ( $registered && !$userOptionsLookup->getBoolOption( $user, Constants::PREF_KEY_LARGER_ELEMENTS ) )
			) {
			$this->options['styles'][] = 'skins.femiwiki.smallElements';
		}
		return parent::getDefaultModules();
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		$skin = $this;
		$out = $skin->getOutput();
		$title = $out->getTitle();
		$config = $this->getConfig();
		$parentData = parent::getTemplateData();
		list( $sidebar, $toolbox ) = $this->getSidebar();

		$commonSkinData = array_merge_recursive( $parentData, [
			'data-sidebar' => $sidebar,
			'html-heading-language-attributes' => $this->prepareHeadingLanguageAttributes(),
			'html-share-button' => $this->getShare(),
			'data-toolbox' => $toolbox,
			'html-lastmod' => $this->lastModified(),
			'text-add-this-pub-id' => $this->getAddThisPubId(),
			'has-footer-icons' => $config->get( Constants::CONFIG_KEY_SHOW_FOOTER_ICONS ),
			'has-indicator' => count( $parentData['array-indicators'] ) !== 0,

			// links
			'link-recentchanges' => SpecialPage::getTitleFor( 'Recentchanges' )->getLocalUrl(),
			'link-random' => SpecialPage::getTitleFor( 'Randompage' )->getLocalUrl(),
			'link-history' => $title->getLocalURL( 'action=history' ),
		] );

		return $commonSkinData;
	}

	/**
	 * Returns data for the sidebar and toolbox.
	 * 'data-portlets-sidebar' also provides it, but it is divided into two parts.
	 * The division is useful for Vector, but not useful for other skins.
	 * @return array
	 */
	protected function getSidebar() {
		$sidebarData = $this->buildSidebar();
		$sidebar = [];
		foreach ( $sidebarData as $name => $items ) {
			if ( !is_array( $items ) ) {
				continue;
			}
			if ( $name == 'TOOLBOX' ) {
				// The toolbox includes both page-specific-tools and site-wide-tools, but we
				// need only page specific tools, so unset those.
				foreach ( [ 'specialpages', 'upload' ] as $item ) {
					unset( $items[$item] );
				}
				$toolbox = $this->getCustomPortletData( $name, $items );
				continue;
			} elseif ( in_array( $name, [ 'SEARCH', 'LANGUAGES' ] ) ) {
				continue;
			}
			$sidebar[] = $this->getCustomPortletData( $name, $items );
		}
		return [ $sidebar, $toolbox ?? null ];
	}

	/**
	 * @return bool
	 */
	protected function shouldShowShare() {
		$title = $this->getOutput()->getTitle();
		if ( !$title ) {
			return false;
		}
		return $title->getArticleID() !== 0;
	}

	/**
	 * @return string|null
	 */
	protected function getShare() {
		if ( !$this->shouldShowShare() ) {
			return null;
		}
		return new ButtonWidget( [
			'id' => 'p-share',
			'classes' => [ 'fw-button' ],
			'infusable' => true,
			'icon' => 'share',
			'title' => $this->msg( 'skin-femiwiki-share-tooltip' )->escaped(),
			'framed' => false,
			'invisibleLabel' => true
		] );
	}

	/**
	 * @return string HTML attributes
	 */
	protected function prepareHeadingLanguageAttributes() {
		$config = $this->getConfig();
		if ( !$config->get( Constants::CONFIG_KEY_USE_PAGE_LANG_FOR_HEADING ) ) {
			return parent::prepareUserLanguageAttributes();
		}
		// Use page language for the first heading.
		$title = $this->getOutput()->getTitle();
		$pageLang = $title->getPageViewLanguage();
		$pageLangCode = $pageLang->getHtmlCode();
		$pageLangDir = $pageLang->getDir();
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();
		if (
			$pageLangCode !== $contLang->getHtmlCode() ||
			$pageLangDir !== $contLang->getDir()
		) {
			$escPageLang = htmlspecialchars( $pageLangCode );
			$escPageDir = htmlspecialchars( $pageLangDir );
			// Attributes must be in double quotes because htmlspecialchars() doesn't
			// escape single quotes
			return " lang=\"$escPageLang\" dir=\"$escPageDir\"";
		}
		return '';
	}

	/**
	 * @return array|mixed
	 */
	private function getXeIconMap() {
		if ( !$this->xeIconMap ) {
			$map = json_decode(
				$this->msg( 'skin-femiwiki-xeicon-map.json' )
					->inContentLanguage()
					->plain(),
				true
			);
			foreach ( $map as $k => $v ) {
				$escapedId = Sanitizer::escapeIdForAttribute( $k );
				if ( $k != $escapedId ) {
					$map[$escapedId] = $v;
					unset( $map[$k] );
				}
			}

			$this->xeIconMap = $map;
		}
		return $this->xeIconMap;
	}

	/**
	 * @param string $menuName
	 * @param string $itemKey
	 * @return string
	 */
	private static function getIconId( $menuName, $itemKey ): string {
		switch ( $menuName ) {
			case 'user-menu':
				return 'pt-' . $itemKey;
			default:
				return 'ca-' . $itemKey;
		}
	}

	/**
	 * Extends to prepend xe-icons
	 *
	 * @inheritDoc
	 */
	protected function runOnSkinTemplateNavigationHooks( $skin, &$content_navigation ) {
		parent::runOnSkinTemplateNavigationHooks( $skin, $content_navigation );
		if ( !$this->isREL1_39 ) {
			return;
		}

		$xeIconMap = $this->getXeIconMap();
		foreach ( $content_navigation as $name => $menuItems ) {
			$icon = null;
			foreach ( $menuItems as $key => $item ) {
				$id = $item['id'] ?? self::getIconId( $name, $key );
				if ( isset( $xeIconMap[$id] ) ) {
					$icon = $xeIconMap[$id];
				}

				if ( $icon ) {
					$item['link-html'] = Html::rawElement(
						'i',
						[
							'class' => 'xi-' . $icon,
						],
						Html::element( 'span', [], $item[ 'text' ] ?? '' )
					);
					$item['text'] = '';
				}
				$content_navigation[$name][$key] = $item;
			}
		}
	}

	/**
	 * @inheritDoc
	 * Can be removed in 1.39
	 */
	public function getPortletData( $name, array $items ) {
		return $this->getCustomPortletData( $name, $items );
	}

	/**
	 * Generate data for a custom p-personal menu
	 * @param string $name
	 * @param array $items
	 * @return array
	 */
	private function getCustomPortletData( $name, array $items ): array {
		$id = Sanitizer::escapeIdForAttribute( "p-$name" );
		$parentData = $this->isREL1_39 ?
			[
				'id' => $id,
				'class' => 'femi-custom-portlet mw-portlet ' . Sanitizer::escapeClass( "mw-portlet-personal" ),
				'html-tooltip' => Linker::tooltip( $id ),
				'html-items' => '',
				'html-after-portal' => '',
				'html-before-portal' => '',
			] : parent::getPortletData( $name, $items );

		$xeIconMap = $this->getXeIconMap();

		$htmlItems = '';
		foreach ( $items as $key => $item ) {
			$id = $item['id'] ?? $name . '-item-' . $key;
			if ( isset( $xeIconMap[$id] ) ) {
				$options = [
					'text-wrapper' => [
						[
							'tag' => 'i',
							'attributes' => [
								'class' => 'xi-' . $xeIconMap[$id]
							]
						],
						[
							'tag' => 'span'
						],
					],
					'link-class' => 'xe-icons',
				];
			}
			$htmlItems .= $this->makeListItem( $key, $item, $options ?? [] );
		}
		if ( $this->isREL1_39 ) {
			$msg = $this->msg( $name );
			$parentData['label'] = $msg->exists() ? $msg->text() : $name;
			$parentData['is-empty'] = count( $items ) === 0;
			$parentData['class'] .= $parentData['is-empty'] ? ' emptyPortlet' : '';
		}
		$parentData['html-items'] = $htmlItems;
		return $parentData;
	}

	/**
	 * Extends Skin::initPage.
	 *
	 * @inheritDoc
	 */
	public function initPage( OutputPage $out ) {
		$out->addMeta( 'viewport', 'width=device-width, initial-scale=1.0' );

		$twitter = $this->getConfig()->get( 'FemiwikiTwitterAccount' );
		if ( $twitter ) {
			$out->addMeta( 'twitter:site', "@$twitter" );
		}

		// Favicons
		$headItems = $this->getConfig()->get( 'FemiwikiHeadItems' );
		if ( $headItems ) {
			$out->addHeadItems( $headItems );
		}

		# Always enable OOUI because OOUI icons are used in FemiwikiTemplate class
		$out->enableOOUI();
		parent::initPage( $out );
	}

	/** @return string|null */
	private function getAddThisPubId() {
		$config = $this->getConfig()->get( 'FemiwikiAddThisId' );
		if ( !$config ) {
			return null;
		}
		if ( is_array( $config ) ) {
			return $config['pub'] ?? null;
		}
		return $config;
	}
}
