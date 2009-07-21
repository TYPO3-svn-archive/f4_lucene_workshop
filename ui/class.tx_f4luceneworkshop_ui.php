<?php
/**
 * Plugin 'tx_f4luceneworkshop_ui' for the 'f4_lucene_workshop' extension.
 * 
 * search mask and result list
 */

require_once(PATH_tslib.'class.tslib_pibase.php');
require_once(t3lib_extMgm::extPath('f4_lucene_workshop').'lib/class.lucene_search_wrapper.php');

class tx_f4luceneworkshop_ui extends tslib_pibase {

	public $pi_checkCHash = TRUE;
	public $prefixId = 'tx_f4luceneworkshop_ui';						// Same as class name
	public $scriptRelPath = 'ui/class.tx_f4luceneworkshop_ui.php';		// Path to this script relative to the extension dir.
	public $extKey = 'f4_lucene_workshop';								// The extension key.
	
	
	private $templateDir = 'typo3conf/ext/f4_lucene_workshop/ui/';	
	private $indexDirectory = 'typo3temp/lucene_search_index';				
	private $luceneSearch = false;
	
	private $teaserTextMaxSize = 300;
	private $itemsPerPage = 10;
	
	function main($content, $conf){
				
 		$this->pi_loadLL(); 
 		$this->templateCode	= file_get_contents($this->templateDir.'template.html');		
 		$this->conf=$conf;
		$this->pi_initPIflexForm();

		$searchParams = array();
		if(t3lib_div::GPvar("search"))
		{
			$searchParams = t3lib_div::GPvar("search");
		}
				
		$start = 0;
		
		if(t3lib_div::GPvar("offset"))
		{
			$offset = intval(t3lib_div::GPvar("offset"));
			if($offset % $this->itemsPerPage != 0)
			{
				$offset = floor($offset / $this->itemsPerPage); 
			}
			$start = $offset;
		}
		
		$searchPhrase = '';
		if(is_array($searchParams) && count($searchParams)>0)
		{
			$searchPhrase = $searchParams['text'];
		}
		
		if(t3lib_div::GPvar("searchphrase"))
		{
			$searchPhrase = urldecode(t3lib_div::GPvar("searchphrase"));
		}	
		
		$resultStr = '';
		if(strlen($searchPhrase)>0)
		{
			$this->luceneSearch = new lucene_search_wrapper($this->indexDirectory);			
			$this->luceneSearch->setQuery($searchPhrase);
			$hits = $this->luceneSearch->find();			
			$resultStr = $this->getResultList($searchPhrase,$hits, $start);			
		}
			
		$content = $this->getSearchForm($searchPhrase, $resultStr);							
		return $content;
 	}
 	
 	function getSearchForm($searchPhrase, $resultStr='')
 	{ 		
		$template = $this->cObj->getSubpart($this->templateCode,"###SEARCHMASK###");
		
		$markers = array();		
		$markers['###HEADLINE###'] = $this->pi_getLL('searchmask.headline');
		$markers['###SUBLINE###'] = $this->pi_getLL('searchmask.subline');
		
		// input term
		$inputTermStr = '<input type="text" name="search[text]" id="txtSearchterm" class="search_field"  value="'.$searchPhrase.'" />';
		$markers['###INPUT_SEARCH_TERM###'] = $inputTermStr;
		
		// submit field 
		$submitStr = '<input type="submit" name="btnSubmit" id="btnSubmit" value="Suchen" class="button" />';
		$markers['###SUBMIT_BUTTON###'] = $submitStr;
		
		// action target
		$markers['###ACTION_TARGET###'] = $this->cObj->typolink_URL(array('parameter'=>$GLOBALS['TSFE']->id, 'additionalParams'=>'&no_cache=1'));		
		
		// result 
		$markers['###RESULT###'] = $resultStr;
		
		$content = 	$this->cObj->substituteMarkerArrayCached($template, $markers, array());
		
		return $content;
 	}
 	
 	function getResultList($searchPhrase, $hits, $start)
 	{ 		
 		$templateResultList = $this->cObj->getSubpart($this->templateCode,"###RESULTLIST###"); 
 		$markersResultList = array();	
 		
 		if(count($hits) > ($start+$this->itemsPerPage)) 		
 			$nextLink = true;
 		else
 			$nextLink = false; 		
 		
 		if($start > 0)
 			$backLink = true;
 		else
 			$backLink = false;
 		
 		$hitsPart = array();
 		
 		if(is_array($hits) &&  count($hits)>0)
 		{
 			if(count($hits) >= ($start + $this->itemsPerPage))
 			{
 				$hitsPart = array_slice($hits, $start, $this->itemsPerPage); 								
 			} elseif ($start < (count($hits)))
 			{				
 				$hitsPart = array_slice($hits, $start, (count($hits)-$start));
 			}
 			 			
 			$resultItemsStr = $this->createItems($hitsPart); 			
 		}
 		
 		$templateResultList = $this->cObj->getSubpart($this->templateCode,"###RESULTLIST###");
		
 		$nextLinkStr = '';
 		$backLinkStr = '';
 		$lastLinkStr = '';
 		$firstLinkStr = '';

 		$curPageStr = 1;
 		$maxPageStr = 1;
 		
 		if($start > 0)
 			$curPageStr = ($start / $this->itemsPerPage)+1;
 		
 		if(count($hits)>$this->itemsPerPage)
 		{
 			$maxPageStr = ceil(count($hits) / $this->itemsPerPage);
 		} 		
 		
 		if($nextLink)
 		{
 			$nextLinkConf = array();
 			$nextLinkConf['parameter'] = $GLOBALS['TSFE']->id;
 			$nextLinkConf['additionalParams'] = '&no_cache=1&offset='.($start+$this->itemsPerPage).'&searchphrase='.urlencode($searchPhrase);
 			
 			$nextLinkStr = $this->cObj->typolink($this->pi_getLL('next_page.link.label'),$nextLinkConf);
 			
 			$lastLinkConf = array();
	 		$lastLinkConf['parameter'] = $GLOBALS['TSFE']->id;
	 		$lastLinkConf['additionalParams'] = '&no_cache=1&offset='.(floor(count($hits)/$this->itemsPerPage)*$this->itemsPerPage).'&searchphrase='.urlencode($searchPhrase);
	 		$lastLinkStr = $this->cObj->typolink($this->pi_getLL('last_page.link.label'),$lastLinkConf);
 		 		
 		}
 		if($backLink)
 		{
 			$backLinkConf = array();
 			$backLinkConf['parameter'] = $GLOBALS['TSFE']->id;
 			$backLinkConf['additionalParams'] = '&no_cache=1&offset='.($start-$this->itemsPerPage).'&searchphrase='.urlencode($searchPhrase);
 			$backLinkStr = $this->cObj->typolink($this->pi_getLL('previous_page.link.label'),$backLinkConf);
 			
 			$firstLinkConf = array();
	 		$firstLinkConf['parameter'] = $GLOBALS['TSFE']->id;
	 		$firstLinkConf['additionalParams'] = '&no_cache=1&offset=0&searchphrase='.urlencode($searchPhrase);
	 		$firstLinkStr = $this->cObj->typolink($this->pi_getLL('first_page.link.label'),$firstLinkConf);
 		} 		
 		 		
 		$hitCount =  ($start+$this->itemsPerPage) > count($hits) ? count($hits) : ($start+$this->itemsPerPage);	
 		
 		$markersResultList['###RESULT_META_INFO###'] = '<strong>'.$this->pi_getLL('hits.label').' '.$start.' '.$this->pi_getLL('to.label').' '.$hitCount.'</strong> '.$this->pi_getLL('from.label').' '.count($hits).' '.$this->pi_getLL('for.label').' <strong>'.$searchPhrase.'</strong>';
 		$markersResultList['###PAGEFIRST###'] = $firstLinkStr;
 		$markersResultList['###PAGENEXT###'] = $nextLinkStr;
 		$markersResultList['###PAGEBACK###'] = $backLinkStr;
 		$markersResultList['###PAGELAST###'] = $lastLinkStr;
 		$markersResultList['###PAGECUR###'] = $curPageStr;
 		$markersResultList['###PAGEMAX###'] = $maxPageStr; 
 		$markersResultList['###RESULT_ITEMS###'] = $resultItemsStr;
 		 		
		$content = 	$this->cObj->substituteMarkerArrayCached($templateResultList, $markersResultList, array());
				
		return $content;		
 	}
 	
	/**
	 * Generate rendered output from all search result items
	 *
	 * @param PowerSearchResultSetInterface $resultset Search result set object
	 * @param int $start Start at position
	 * @param int $stop Stop at position
	 * @return string Code fragment with all results from start to stop
	 */

	protected function createItems($hits)
	{		
		$list = array();
		$items = array();
		$marker = array();
		
		$queryTermsObjArr = $this->luceneSearch->getQueryTerms();
		$queryTermsArr = array();
				
		foreach($queryTermsObjArr as $queryTermObj)
		{
			$queryTermsArr[] = $queryTermObj->text;
		}		

		$content = '';
		
		foreach($hits as $hit)
		{			
			$searchResultPageUid = 0;
			$searchResultPageTitle = '';
			$searchResultPageTeaserText = '';
			$searchResultPageTeaserText_Highlighted = '';				
						
			$document = $hit->getDocument();
												
			$fieldNamesArr = $document->getFieldNames();
							
			foreach($fieldNamesArr as $fieldName)
			{
				$fieldValue = $document->getFieldValue($fieldName);
				
				if($fieldName == 'title')
				{
					$searchResultPageTitle = $fieldValue;
				}
				
				if($fieldName == 'ref')
				{
					$searchResultPageUid = $fieldValue;
				}
				
				// now render the teasertext from abstract or bodytext					
				if($fieldName == 'abstract')
				{
					// cut the part from the bodytext where one of the search terms is located
					foreach($queryTermsArr as $queryTerm)
					{
						if(stripos($fieldValue,$queryTerm)!==false)
						{
							// overwrite highlighted text in every case if search phrase was found in the abstract
							$searchResultPageTeaserText_Highlighted = $this->luceneSearch->highlightMatches(utf8_decode($fieldValue));																							
						}
					}	
					if(!$searchResultPageTeaserText_Highlighted)
					{
						$searchResultPageTeaserTextArr = $fieldValue;
					}					
				}
				
				if($fieldName == 'bodytext')
				{
					if(!$searchResultPageTeaserText_Highlighted)
					{					
						// cut the part from the bodytext where one of the search terms is located
						foreach($queryTermsArr as $queryTerm)
						{
							if(stripos($fieldValue,$queryTerm)!==false)
							{								
								$searchResultPageTeaserText_Highlighted = $this->getHighlightedCuttedTeaserText($fieldValue,$queryTerm)	;																								
							}
						}					
					}
					if(!$searchResultPageTeaserText_Highlighted && !$searchResultPageTeaserText)
					{
						$searchResultPageTeaserTextArr = $this->getTextTokenized($fieldValue);
						$searchResultPageTeaserText = $searchResultPageTeaserTextArr[0];
					}
				}					
			}

			$itemStr = '';
			$searchResultLink = $this->cObj->typolink($searchResultPageTitle,array('parameter'=>$searchResultPageUid));
			$itemStr .= '<h2>'.$searchResultLink.'</h2>';
			$itemStr .= '<p>';
			
			
			$itemStr .= $searchResultPageTeaserText_Highlighted ? $searchResultPageTeaserText_Highlighted : $searchResultPageTeaserText;
			$itemStr .= '</p>';		
			$itemStr = '<div class="box"><div class="text">'.$itemStr.'</div></div>';
			
			$content .= $itemStr;
		}	
		
		return $content;
	}
 	
	function getHighlightedCuttedTeaserText($text, $searchTermStr)
	{		
		$textArr = $this->getTextTokenized($text);
		
		$i = 0;
		foreach($textArr as $textToken)
		{
			if(stripos($textToken,$searchTermStr)!==false)
			{				
				$resultStr = '';
				if($i > 0) 
					$textToken = '[..] '.$textToken;
				if($i < (count($textArr)-1)) 
					$textToken = $textToken.' [..]';
				$resultStr = $this->luceneSearch->highlightMatches(utf8_decode($textToken));
				
				return $resultStr;
			}
			
			$i++;
		}
		return '';					
	}
	
	function getTextTokenized($text)
	{
		$explodeMarker = '###explode###';
		$text = wordwrap($text, $this->teaserTextMaxSize, '###explode###');
		$textArr = explode($explodeMarker,$text);
		return $textArr;
	}
 	
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/f4_lucene_workshop/ui/class.tx_f4luceneworkshop_ui.php']) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/f4_lucene_workshop/ui/class.tx_f4luceneworkshop_ui.php']);
}
?>
