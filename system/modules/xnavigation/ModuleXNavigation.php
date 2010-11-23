<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * TYPOlight Open Source CMS
 * Copyright (C) 2009-2010 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  InfinityLabs - Olck & Lins GbR - 2009-2010
 * @author     Tristan Lins <tristan.lins@infinitylabs.de>
 * @package    xNavigation
 * @license    LGPL 
 * @filesource
 */


/**
 * Class ModuleXNavigation
 *
 * Front end module "xNavigation".
 * @copyright  InfinityLabs - Olck & Lins GbR - 2009-2010
 * @author     Tristan Lins <tristan.lins@infinitylabs.de>
 * @package    xNavigation
 */
class ModuleXNavigation extends Module {
	
	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_navigation';
	
	protected $objModule;
	
	
	/**
	 * Initialize the object
	 * @param object
	 * @param string
	 */
	public function __construct(Database_Result $objModule, $strColumn='main')
	{
		parent::__construct($objModule);
		$this->objModule = &$objModule;
	}
	

	/**
	 * Do not display the module if there are no menu items
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### NAVIGATION MENU ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = 'typolight/main.php?do=modules&amp;act=edit&amp;id=' . $this->id;

			return $objTemplate->parse();
		}

		$strBuffer = parent::generate();
		return strlen($this->Template->items) ? $strBuffer : '';
	}

	
	/**
	 * Generate module
	 */
	protected function compile()
	{
		global $objPage;
		
		$trail = $objPage->trail;
		$level = ($this->levelOffset > 0) ? $this->levelOffset : 0;

		// Overwrite with custom reference page
		if ($this->defineRoot && $this->rootPage > 0)
		{
			$trail = array($this->rootPage);
			$level = 0;
		}

		$request = ampersand($this->Environment->request, true);

		if ($request == 'index.php')
		{
			$request = '';
		}

		$this->Template->request = $request;
		$this->Template->skipId = 'skipNavigation' . $this->id;
		$this->Template->skipNavigation = specialchars($GLOBALS['TL_LANG']['MSC']['skipNavigation']);
		$this->Template->items = $this->renderXNavigation($trail[$level], $level+1);
	}
	
	
	/**
	 * Converts the articles array structure into a 1 dimension flat structure, that only contains the top level.
	 * All subitems are converted and parsed by the navigation template.
	 * 
	 * @param Database_Result FrontendTemplate
	 * @param mixed $articles
	 * @param integer $level
	 * @param boolean $toString
	 * @return array
	 */
	protected function convertArticles2Navigation(FrontendTemplate &$objTemplate, $articles, $level, $toString = false) {
		foreach ($articles as &$article) {
			// Set the item link
			$article['link'] = $article['title'];
			// Flatten subitems
			if (isset($article['subitems'])) {
				$article['subitems'] = $this->convertArticles2Navigation($objTemplate, $article['subitems'], $level + 1, true);
			}
		}
		
		// Add classes first and last
		if (count($articles))
		{
			$last = count($articles) - 1;

			$articles[0]['class'] = trim($articles[0]['class'] . ' first_article' . ($toString ? ' first' : ''));
			$articles[$last]['class'] = trim($articles[$last]['class'] . ' last_article' . ($toString ? ' last' : ''));
		}
		
		// Parse by template or return the modified array
		if ($toString) {
			$objTemplate->level = 'level_' . $level++;
			$objTemplate->items = $articles;
			return $objTemplate->parse();
		} else {
			return $articles;
		}
	}
	
	/**
	 * Generate the news archive items.
	 * 
	 * @param Database_Result $objCurrentPage
	 * @param array $objNewsArchives
	 * @param array $items
	 * @param integer $time
	 */
	protected function generateNewsItems(Database_Result &$objCurrentPage, &$objNewsArchives, &$items, $time) {
		$arrData = array();
		$maxQuantity = 0;
		switch ($objCurrentPage->xNavigationNewsArchiveFormat) {
		case 'news_year':
			$format = 'Y';
			$param = 'year';
			break;
		case 'news_month':
		default:
			$format = 'Ym';
			$param = 'month';
		}
		
		foreach ($objNewsArchives as $id)
		{
			// Get all active items
			$objArchives = $this->Database->prepare("SELECT date FROM tl_news WHERE pid=?" . (!BE_USER_LOGGED_IN ? " AND (start='' OR start<$time) AND (stop='' OR stop>$time) AND published=1" : "") . " ORDER BY date DESC")
										  ->execute($id);

			while ($objArchives->next())
			{
				++$arrData[date($format, $objArchives->date)];
				if ($arrData[date($format, $objArchives->date)] > $maxQuantity) {
					$maxQuantity = $arrData[date($format, $objArchives->date)];
				}
			}
		}
		krsort($arrData);
		
		$url = $this->generateFrontendUrl($objCurrentPage->row(), sprintf('/%s/%%s', $param));
		
		if (count($arrData)) {
			$n = count($items);
			foreach ($arrData as $intDate => $intCount) {
				$quantity = sprintf((($intCount < 2) ? $GLOBALS['TL_LANG']['MSC']['entry'] : $GLOBALS['TL_LANG']['MSC']['entries']), $intCount);
				switch ($objCurrentPage->xNavigationNewsArchiveFormat) {
				case 'news_year':
					$intYear = $intDate;
					$intMonth = '0';
					$link = $title = specialchars($intYear . ($objCurrentPage->xNavigationNewsArchiveShowQuantity=='1' ? ' (' . $quantity . ')' : ''));
					break;
				case 'news_month':
				default:
					$intYear = intval(substr($intDate, 0, 4));
					$intMonth = intval(substr($intDate, 4));
					$link = $title = specialchars($GLOBALS['TL_LANG']['MONTHS'][$intMonth-1].' '.$intYear . ($objCurrentPage->xNavigationNewsArchiveShowQuantity=='1' ? ' (' . $quantity . ')' : ''));
				}
				
				$items[] = array(
					'date' => $intDate,
					'link' => $link,
					'href' => sprintf($url, $intDate),
					'title' => $title,
					'isActive' => ($this->Input->get($param) == $intDate),
					'quantity' => $quantity,
					'maxQuantity' => $maxQuantity,
					'itemtype' => 'news_archive',
					'class' => ''
				);
			}
			
			$last = count($items) - 1;
			
			$items[$n]['class'] = trim($items[$n]['class'] . ' first_news_archive');
			$items[$last]['class'] = trim($items[$last]['class'] . ' last_news_archive');
		}
	}
	
	/**
	 * Recursively compile the navigation menu and return it as HTML string
	 * @param mixed $objCurrentPage
	 * @param integer $level
	 * @return string
	 */
	protected function renderXNavigation(&$objCurrentPage, $level=1) {
		$time = time();
		
		// Get global page object
		global $objPage;
		
		// Convert current page id into database record 
		if (is_numeric($objCurrentPage))
		{
			if ($objCurrentPage > 0) {
				$objCurrentPage = $this->Database->prepare("SELECT * FROM tl_page WHERE id = ?")
									   ->execute($objCurrentPage);
				if (!$objCurrentPage->next())
				{
					return '';
				}
				$objCurrentPageID = intval($objCurrentPage->id);
			} else {
				$objCurrentPageID = intval($objCurrentPage);
			}
		} else {
			$objCurrentPageID = intval($objCurrentPage->id);
		}
		
		// Define if the current element is active
		$active = $objCurrentPageID == $objPage->id || in_array($objCurrentPageID, $objPage->trail);
		
		// Get all active subpages
		$objSubpages = $this->Database->prepare("SELECT p1.*, 
			(SELECT COUNT(*) FROM tl_page p2 WHERE p2.pid=p1.id AND p2.type!='root' AND p2.type!='error_403' AND p2.type!='error_404'" . ((FE_USER_LOGGED_IN && !BE_USER_LOGGED_IN) ? " AND p2.guests!=1" : "") . (!BE_USER_LOGGED_IN ? " AND (p2.start='' OR p2.start<".$time.") AND (p2.stop='' OR p2.stop>".$time.") AND p2.published=1" : "") . ") AS subpages,
			(SELECT COUNT(*) FROM tl_page p2 WHERE p2.pid=p1.id AND p2.type!='root' AND p2.type!='error_403' AND p2.type!='error_404'" . ((FE_USER_LOGGED_IN && !BE_USER_LOGGED_IN) ? " AND p2.guests!=1" : "") . (!BE_USER_LOGGED_IN ? " AND (p2.start='' OR p2.start<".$time.") AND (p2.stop='' OR p2.stop>".$time.") AND p2.published=1" : "") . " AND hide != 1 AND xNavigation != 'map_never') AS vsubpages
			FROM tl_page p1 WHERE p1.pid=? ".($this instanceof ModuleXSitemap ? "" : "AND p1.type!='root' ")."AND p1.type!='error_403' AND p1.type!='error_404'" . ((FE_USER_LOGGED_IN && !BE_USER_LOGGED_IN && !$this->showProtected) ? " AND p1.guests!=1" : "") . (!BE_USER_LOGGED_IN ? " AND (p1.start='' OR p1.start<".$time.") AND (p1.stop='' OR p1.stop>".$time.") AND p1.published=1" : "") . " ORDER BY p1.sorting")
									  ->execute($objCurrentPageID);
		
		// Get article navigation
		if ($objCurrentPageID > 0 && ($objCurrentPage->xNavigationIncludeArticles == 'map_always' || ($this instanceof ModuleXSitemap || $active) && $objCurrentPage->xNavigationIncludeArticles == 'map_active'))
			$objArticles = $this->Database->prepare("SELECT id FROM tl_article WHERE pid = ? AND xNavigation != 'map_never' AND inColumn = 'main'" . ((FE_USER_LOGGED_IN && !BE_USER_LOGGED_IN && !$this->showProtected) ? " AND guests!=1" : "") . (!BE_USER_LOGGED_IN ? " AND (start='' OR start<".$time.") AND (stop='' OR stop>".$time.") AND published=1" : ""))
										  ->execute($objCurrentPageID);
		else
			$objArticles = false;

		// Get news navigation
		if ($objCurrentPageID > 0 && ($objCurrentPage->xNavigationIncludeNewsArchives == 'map_always' || ($this instanceof ModuleXSitemap || $active) && $objCurrentPage->xNavigationIncludeNewsArchives == 'map_active'))
			$objNewsArchives = unserialize($objCurrentPage->xNavigationNewsArchives);
		else
			$objNewsArchives = false;
		
		$items = array();
		$groups = array();

		// Get all groups of the current front end user
		if (FE_USER_LOGGED_IN)
		{
			$this->import('FrontendUser', 'User');
			$groups = $this->User->groups;
		}

		// Layout template fallback
		if (!strlen($this->navigationTpl))
		{
			$this->navigationTpl = 'nav_default';
		}

		$objTemplate = new FrontendTemplate($this->navigationTpl);

		$objTemplate->type = get_class($this);

		// Render article navigation
		if ($objArticles) {
			$articleIDs = array();
			while($objArticles->next()) {
				$articleIDs[] = $objArticles->id;
			}
			$an = new ArticleNavigation();
			$items = array_merge($items, $this->convertArticles2Navigation($objTemplate, $an->fromArticles($articleIDs), $level));
		}
		
		// Render news navigation
		if ($objNewsArchives) {
			$this->generateNewsItems($objCurrentPage, $objNewsArchives, $items, $time);
		}

		$objTemplate->level = 'level_' . $level;
		
		// Browse subpages
		$n = count($items);
		while($objSubpages->next())
		{
			// Skip hidden pages
			if (/* non sitemap navigation */
				!($this instanceof ModuleXSitemap) && ($objSubpages->xNavigation == 'map_never' || $objSubpages->hide ||
					($this->showLevel > 0 && $this->showLevel < $level && 
						!($objPage->id == $objSubpages->id ||
							in_array($objSubpages->id, $objPage->trail) ||
							in_array($objCurrentPageID, $objPage->trail)) ||
						$this->hardLevel > 0 && $this->hardLevel < $level) && $objSubpages->xNavigation != 'map_always') ||
				/* sitemap navigation */
				$this instanceof ModuleXSitemap && $objSubpages->sitemap == 'map_never')
			{
				continue;
			}
			
			$subitems = '';
			$_groups = deserialize($objSubpages->groups);

			// Do not show protected pages unless a back end or front end user is logged in
			if (!strlen($objSubpages->protected) || BE_USER_LOGGED_IN || (!is_array($_groups) && FE_USER_LOGGED_IN) || (is_array($_groups) && count(array_intersect($groups, $_groups))) || $this->showProtected || ($this instanceof ModuleSitemap && $objSubpages->sitemap == 'map_always'))
			{
				// Check whether there will be subpages
				if ($objSubpages->subpages > 0 || $objSubpages->xNavigationIncludeArticles != 'map_never' || $objSubpages->xNavigationIncludeNewsArchives != 'map_never')
				{
					$subitems = $this->renderXNavigation($objSubpages, $level+1);
				}

				// Get href
				switch ($objSubpages->type)
				{
					case 'redirect':
						$href = $objSubpages->url;

						if (strncasecmp($href, 'mailto:', 7) === 0)
						{
							$this->import('String');
							$href = $this->String->encodeEmail($href);
						}
						break;

					case 'forward':
						if (!$objSubpages->jumpTo)
						{
							$objNext = $this->Database->prepare("SELECT id, alias FROM tl_page WHERE pid=? AND type='regular'" . (!BE_USER_LOGGED_IN ? " AND (start='' OR start<$time) AND (stop='' OR stop>$time) AND published=1" : "") . " ORDER BY sorting")
													  ->limit(1)
													  ->execute($objSubpages->id);
						}
						else
						{
							$objNext = $this->Database->prepare("SELECT id, alias FROM tl_page WHERE id=?")
													  ->limit(1)
													  ->execute($objSubpages->jumpTo);
						}

						if ($objNext->numRows)
						{
							$href = $this->generateFrontendUrl($objNext->fetchAssoc());
							break;
						}
						// DO NOT ADD A break; STATEMENT

					default:
						$href = $this->generateFrontendUrl($objSubpages->row());
						break;
				}
				
				$hassubmenu = false;
				if (!($this instanceof ModuleXSitemap) &&
					($objSubpages->xNavigationIncludeArticles == 'map_always' || $objSubpages->xNavigationIncludeArticles == 'map_active' ||
					$objSubpages->xNavigationIncludeNewsArchives == 'map_always' || $objSubpages->xNavigationIncludeNewsArchives == 'map_active' ||
					$objSubpages->vsubpages > 0)) {
					$hassubmenu = true;
				}
				

				// Active page
				if (($objPage->id == $objSubpages->id || $objSubpages->type == 'forward' && $objPage->id == $objSubpages->jumpTo)
					&& !$this instanceof ModuleXSitemap && !$this->Input->get('articles'))
				{
					$strClass = 'page' . (strlen($subitems) ? ' submenu' : '') . ($hassubmenu ? ' hassubmenu' : '') . (strlen($objSubpages->cssClass) ? ' ' . $objSubpages->cssClass : '');
					$row = $objSubpages->row();

					$row['isActive'] = true;
					$row['subitems'] = $subitems;
					$row['class'] = (strlen($strClass) ? $strClass : '');
					$row['pageTitle'] = specialchars($objSubpages->pageTitle);
					$row['title'] = specialchars($objSubpages->title);
					$row['link'] = $objSubpages->title;
					$row['href'] = $href;
					$row['alias'] = $objSubpages->alias;
					$row['nofollow'] = (strncmp($objSubpages->robots, 'noindex', 7) === 0);
					$row['target'] = (($objSubpages->type == 'redirect' && $objSubpages->target) ? LINK_NEW_WINDOW : '');
					$row['description'] = str_replace(array("\n", "\r"), array(' ' , ''), $objSubpages->description);
					$row['accesskey'] = $objSubpages->accesskey;
					$row['tabindex'] = $objSubpages->tabindex;
					$row['subpages'] = $objSubpages->subpages;
					$row['itemtype'] = 'page';

					$items[] = $row;
				}

				// Regular page
				else
				{
					$strClass = 'page' . (strlen($subitems) ? ' submenu' : '') . ($hassubmenu ? ' hassubmenu' : '') . (strlen($objSubpages->cssClass) ? ' ' . $objSubpages->cssClass : '') . (in_array($objSubpages->id, $objPage->trail) ? ' trail' : '');
					
					$row = $objSubpages->row();

					$row['isActive'] = false;
					$row['subitems'] = $subitems;
					$row['class'] = (strlen($strClass) ? $strClass : '');
					$row['pageTitle'] = specialchars($objSubpages->pageTitle);
					$row['title'] = specialchars($objSubpages->title);
					$row['link'] = $objSubpages->title;
					$row['href'] = $href;
					$row['alias'] = $objSubpages->alias;
					$row['nofollow'] = (strncmp($objSubpages->robots, 'noindex', 7) === 0);
					$row['target'] = (($objSubpages->type == 'redirect' && $objSubpages->target) ? LINK_NEW_WINDOW : '');
					$row['description'] = str_replace(array("\n", "\r"), array(' ' , ''), $objSubpages->description);
					$row['accesskey'] = $objSubpages->accesskey;
					$row['tabindex'] = $objSubpages->tabindex;
					$row['subpages'] = $objSubpages->subpages;
					$row['itemtype'] = 'page';

					$items[] = $row;
				}
			}
		}

		// Add classes first and last
		if (count($items))
		{
			$last = count($items) - 1;
			
			if ($n <= $last) {
				$items[$n]['class'] = trim($items[$n]['class'] . ' first_page');
				$items[$last]['class'] = trim($items[$last]['class'] . ' last_page');
			}

			$items[0]['class'] = trim($items[0]['class'] . ' first');
			$items[$last]['class'] = trim($items[$last]['class'] . ' last');
		}

		$objTemplate->items = $items;
		return count($items) ? $objTemplate->parse() : '';
	}
	
}

?>