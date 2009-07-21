<?php

require_once (t3lib_extMgm::extPath('indexed_search').'class.indexer.php');
	require_once 'Zend/Search/Lucene.php';

class ux_tx_indexedsearch_indexer extends tx_indexedsearch_indexer {
	
	var $debug = false;
	var $indexOptimize = false;

	/**
	 * Parent Object (TSFE) Initialization
	 *
	 * @param	object		Parent Object (frontend TSFE object), passed by reference
	 * @return	void
	 */
	function hook_indexContent(&$pObj)	{
		
			// Indexer configuration from Extension Manager interface:
		$indexerConfig = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['indexed_search']);

			// Crawler activation:
			// Requirements are that the crawler is loaded, a crawler session is running and re-indexing requested as processing instruction:
		if (t3lib_extMgm::isLoaded('crawler')
				&& $pObj->applicationData['tx_crawler']['running']
				&& in_array('tx_indexedsearch_reindex', $pObj->applicationData['tx_crawler']['parameters']['procInstructions']))	{

				// Setting simple log message:
			$pObj->applicationData['tx_crawler']['log'][] = 'Forced Re-indexing enabled';

				// Setting variables:
			$this->crawlerActive = TRUE;	// Crawler active flag
			$this->forceIndexing = TRUE;	// Force indexing despite timestamps etc.
		}

			// Determine if page should be indexed, and if so, configure and initialize indexer
		if ($pObj->config['config']['index_enable'])	{
			$this->log_push('Index page','');

			if (!$indexerConfig['disableFrontendIndexing'] || $this->crawlerActive)	{
				if (!$pObj->page['no_search'])	{
					if (!$pObj->no_cache)	{
						if (!strcmp($pObj->sys_language_uid,$pObj->sys_language_content))	{
			
							// Setting up internal configuration from config array:
							$this->conf = array();
							
							// TODO IMPLEMENT AS HOOK (jhh) additional index fields
							$this->conf['doktype'] = $pObj->page['doktype'];
							$this->conf['lastUpdated'] = $pObj->page['lastUpdated'];
							$this->conf['author'] = $pObj->page['author'];
							$this->conf['nav_title'] = $pObj->page['nav_title'];
							$this->conf['tx_realurl_pathsegment'] = $pObj->page['tx_realurl_pathsegment'];
							$this->conf['fe_group'] = $pObj->page['fe_group'];
														
							
								// Information about page for which the indexing takes place
							$this->conf['id'] = $pObj->id;				// Page id
							$this->conf['type'] = $pObj->type;			// Page type
							$this->conf['sys_language_uid'] = $pObj->sys_language_uid;	// sys_language UID of the language of the indexing.
							$this->conf['MP'] = $pObj->MP;				// MP variable, if any (Mount Points)
							$this->conf['gr_list'] = $pObj->gr_list;	// Group list

							$this->conf['cHash'] = $pObj->cHash;					// cHash string for additional parameters
							$this->conf['cHash_array'] = $pObj->cHash_array;		// Array of the additional parameters

							$this->conf['crdate'] = $pObj->page['crdate'];			// The creation date of the TYPO3 page
							$this->conf['page_cache_reg1'] = $pObj->page_cache_reg1;	// reg1 of the caching table. Not known what practical use this has.

								// Root line uids
							$this->conf['rootline_uids'] = array();
							foreach($pObj->config['rootLine'] as $rlkey => $rldat)	{
								$this->conf['rootline_uids'][$rlkey] = $rldat['uid'];
							}

								// Content of page:
							$this->conf['content'] = $pObj->content;					// Content string (HTML of TYPO3 page)
							$this->conf['indexedDocTitle'] = $pObj->convOutputCharset($pObj->indexedDocTitle);	// Alternative title for indexing
							$this->conf['metaCharset'] = $pObj->metaCharset;			// Character set of content (will be converted to utf-8 during indexing)
							$this->conf['mtime'] = $pObj->register['SYS_LASTCHANGED'];	// Most recent modification time (seconds) of the content on the page. Used to evaluate whether it should be re-indexed.

								// Configuration of behavior:
							$this->conf['index_externals'] = $pObj->config['config']['index_externals'];	// Whether to index external documents like PDF, DOC etc. (if possible)
							$this->conf['index_descrLgd'] = $pObj->config['config']['index_descrLgd'];		// Length of description text (max 250, default 200)
							$this->conf['index_metatags'] = isset($pObj->config['config']['index_metatags']) ? $pObj->config['config']['index_metatags'] : true;

								// Set to zero:
							$this->conf['recordUid'] = 0;
							$this->conf['freeIndexUid'] = 0;
							$this->conf['freeIndexSetId'] = 0;

								// Init and start indexing:
							$this->init();
							$this->indexTypo3PageContent();
						} else $this->log_setTSlogMessage('Index page? No, ->sys_language_uid was different from sys_language_content which indicates that the page contains fall-back content and that would be falsely indexed as localized content.');
					} else $this->log_setTSlogMessage('Index page? No, page was set to "no_cache" and so cannot be indexed.');
				} else $this->log_setTSlogMessage('Index page? No, The "No Search" flag has been set in the page properties!');
			} else $this->log_setTSlogMessage('Index page? No, Ordinary Frontend indexing during rendering is disabled.');
			$this->log_pull();
		}
	}

	/********************************
	 *
	 * Indexing; TYPO3 pages (HTML content)
	 *
	 *******************************/

	/**
	 * Start indexing of the TYPO3 page
	 *
	 * @return	void
	 */
	function indexTypo3PageContent()	{
		
		$check = $this->checkMtimeTstamp($this->conf['mtime'], $this->hash['phash']);
		$is_grlist = $this->is_grlist_set($this->hash['phash']);

		if ($check > 0 || !$is_grlist || $this->forceIndexing)	{

			// Setting message:
			if ($this->forceIndexing)	{
				$this->log_setTSlogMessage('Indexing needed, reason: Forced',1);
			} elseif ($check > 0)	{
				$this->log_setTSlogMessage('Indexing needed, reason: '.$this->reasons[$check],1);
			} else {
				$this->log_setTSlogMessage('Indexing needed, reason: Updates gr_list!',1);
			}

					// Divide into title,keywords,description and body:
			$this->log_push('Split content','');
				$this->contentParts = $this->splitHTMLContent($this->conf['content']);
				if ($this->conf['indexedDocTitle'])	{
					$this->contentParts['title'] = $this->conf['indexedDocTitle'];
				}
			$this->log_pull();

				// Calculating a hash over what is to be the actual page content. Maybe this hash should not include title,description and keywords? The bodytext is the primary concern. (on the other hand a changed page-title would make no difference then, so dont!)
			$this->content_md5h = $this->md5inthash(implode($this->contentParts,''));

				// This function checks if there is already a page (with gr_list = 0,-1) indexed and if that page has the very same contentHash.
				// If the contentHash is the same, then we can rest assured that this page is already indexed and regardless of mtime and origContent we don't need to do anything more.
				// This will also prevent pages from being indexed if a fe_users has logged in and it turns out that the page content is not changed anyway. fe_users logged in should always search with hash_gr_list = "0,-1" OR "[their_group_list]". This situation will be prevented only if the page has been indexed with no user login on before hand. Else the page will be indexed by users until that event. However that does not present a serious problem.
			$checkCHash = $this->checkContentHash();
			if (!is_array($checkCHash) || $check===1)	{
				$Pstart=t3lib_div::milliseconds();

				$this->log_push('Converting charset of content ('.$this->conf['metaCharset'].') to utf-8','');
					$this->charsetEntity2utf8($this->contentParts,$this->conf['metaCharset']);
				$this->log_pull();

						// Splitting words
				$this->log_push('Extract words from content','');
					$splitInWords = $this->processWordsInArrays($this->contentParts);
				$this->log_pull();

						// Analyse the indexed words.
				$this->log_push('Analyse the extracted words','');
					$indexArr = $this->indexAnalyze($splitInWords);
				$this->log_pull();

						// Submitting page (phash) record
				$this->log_push('Submitting page','');

				// TODO REMOVE (jhh)
				echo "CALL: submitPage<br/>";
				$this->submitPage();
				
				$this->log_pull();

						// Check words and submit to word list if not there
				$this->log_push('Check word list and submit words','');
					$this->checkWordList($indexArr);
					$this->submitWords($indexArr,$this->hash['phash']);
				$this->log_pull();

						// Set parsetime
				$this->updateParsetime($this->hash['phash'],t3lib_div::milliseconds()-$Pstart);

						// Checking external files if configured for.
				$this->log_push('Checking external files','');
				if ($this->conf['index_externals'])	{
					$this->extractLinks($this->conf['content']);
				}
				$this->log_pull();
			} else {
				$this->updateTstamp($this->hash['phash'],$this->conf['mtime']);	// Update the timestatmp
				$this->updateSetId($this->hash['phash']);
				$this->update_grlist($checkCHash['phash'],$this->hash['phash']);	// $checkCHash['phash'] is the phash of the result row that is similar to the current phash regarding the content hash.
				$this->updateRootline();
				$this->log_setTSlogMessage('Indexing not needed, the contentHash, '.$this->content_md5h.', has not changed. Timestamp, grlist and rootline updated if necessary.');
			}
		} else {
		
			// echo "DEBUG POINT: else check forceIndexing ...<br/>";
		
			$this->log_setTSlogMessage('Indexing not needed, reason: '.$this->reasons[$check]);
		}
	}

	function getAbstractOfPage($uid)
	{
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('abstract', 'pages', 'uid='.$uid);
		if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			return $row['abstract'];
		} else return '';
		
	}

	/********************************
	 *
	 * SQL; TYPO3 Pages
	 *
	 *******************************/

	/**
	 * Updates db with information about the page (TYPO3 page, not external media)
	 *
	 * @return	void
	 */
	function submitPage()	{
	
		// TODO REMOVE (jhh)
		echo "CALL: submitPage<br/>";

		// Remove any current data for this phash:
		$this->removeOldIndexedPages($this->hash['phash']);

			// setting new phash_row
		$fields = array(
			'phash' => $this->hash['phash'],
			'phash_grouping' => $this->hash['phash_grouping'],
			'cHashParams' => serialize($this->cHashParams),
			'contentHash' => $this->content_md5h,
			'data_page_id' => $this->conf['id'],
			'data_page_reg1' => $this->conf['page_cache_reg1'],
			'data_page_type' => $this->conf['type'],
			'data_page_mp' => $this->conf['MP'],
			'gr_list' => $this->conf['gr_list'],
			'item_type' => 0,	// TYPO3 page
			'item_title' => $this->contentParts['title'],
			'item_description' => $this->bodyDescription($this->contentParts),
			'item_mtime' => $this->conf['mtime'],
			'item_size' => strlen($this->conf['content']),
			'tstamp' => time(),
			'crdate' => time(),
			'item_crdate' => $this->conf['crdate'],	// Creation date of page
			'sys_language_uid' => $this->conf['sys_language_uid'],	// Sys language uid of the page. Should reflect which language it DOES actually display!
 			'externalUrl' => 0,
 			'recordUid' => intval($this->conf['recordUid']),
 			'freeIndexUid' => intval($this->conf['freeIndexUid']),
 			'freeIndexSetId' => intval($this->conf['freeIndexSetId']),
		);
				
		// create an array with data for the index
		$elements = array();
		$elements['type'] = $fields['item_type'];
		$elements['ref'] = $fields['data_page_id'];
		$elements['title'] = $fields['item_title'];
		$elements['description'] = $this->bodyDescription($this->contentParts);
		$elements['text'] = $this->bodyDescription($this->contentParts);
		$elements['crdate'] = $fields['crdate'];
		$elements['keywords'] = $this->contentParts['keywords'];		
		$elements['abstract'] = $this->getAbstractOfPage($fields['data_page_id']);			
							
		
		$index = $this->createIndex();
		$doc = $this->createDocument($elements);
				
		$this->deleteFromLuceneIndex($index,$fields['data_page_id']);
		$index->addDocument($doc);				
		
		$GLOBALS['TYPO3_DB']->exec_INSERTquery('index_phash', $fields);

			// PROCESSING index_section
		$this->submit_section($this->hash['phash'],$this->hash['phash']);

			// PROCESSING index_grlist
		$this->submit_grlist($this->hash['phash'],$this->hash['phash']);

			// PROCESSING index_fulltext
		$fields = array(
			'phash' => $this->hash['phash'],
			'fulltextdata' => implode(' ', $this->contentParts)
		);
		if ($this->indexerConfig['fullTextDataLength']>0)	{
			$fields['fulltextdata'] = substr($fields['fulltextdata'],0,$this->indexerConfig['fullTextDataLength']);
		}
		$GLOBALS['TYPO3_DB']->exec_INSERTquery('index_fulltext', $fields);

			// PROCESSING index_debug
		if ($this->indexerConfig['debugMode'])	{
			$fields = array(
				'phash' => $this->hash['phash'],
				'debuginfo' => serialize(array(
						'cHashParams' => $this->cHashParams,
						'external_parsers initialized' => array_keys($this->external_parsers),
						'conf' => array_merge($this->conf,array('content'=>substr($this->conf['content'],0,1000))),
						'contentParts' => array_merge($this->contentParts,array('body' => substr($this->contentParts['body'],0,1000))),
						'logs' => $this->internal_log,
						'lexer' => $this->lexerObj->debugString,
					))
			);
			$GLOBALS['TYPO3_DB']->exec_INSERTquery('index_debug', $fields);
		}
	}


	/**
	 * Removes records for the indexed page, $phash
	 *
	 * @param	integer		phash value to flush
	 * @return	void
	 */
	function removeOldIndexedPages($phash)	{
			// Removing old registrations for all tables. Because the pages are TYPO3 pages there can be nothing else than 1-1 relations here.
		$tableArr = explode(',','index_phash,index_section,index_grlist,index_fulltext,index_debug');
		foreach($tableArr as $table)	{
			$GLOBALS['TYPO3_DB']->exec_DELETEquery($table, 'phash='.intval($phash));
		}
			// Removing all index_section records with hash_t3 set to this hash (this includes such records set for external media on the page as well!). The re-insert of these records are done in indexRegularDocument($file).
		$GLOBALS['TYPO3_DB']->exec_DELETEquery('index_section', 'phash_t3='.intval($phash));
	}


	/********************************
	 *
	 * SQL; External media
	 *
	 *******************************/


	/**
	 * Updates db with information about the file
	 *
	 * @param	array		Array with phash and phash_grouping keys for file
	 * @param	string		File name
	 * @param	array		Array of "cHashParams" for files: This is for instance the page index for a PDF file (other document types it will be a zero)
	 * @param	string		File extension determining the type of media.
	 * @param	integer		Modification time of file.
	 * @param	integer		Creation time of file.
	 * @param	integer		Size of file in bytes
	 * @param	integer		Content HASH value.
	 * @param	array		Standard content array (using only title and body for a file)
	 * @return	void
	 */
	function submitFilePage($hash,$file,$subinfo,$ext,$mtime,$ctime,$size,$content_md5h,$contentParts)	{

			// Find item Type:
		$storeItemType = $this->external_parsers[$ext]->ext2itemtype_map[$ext];
		$storeItemType = $storeItemType ? $storeItemType : $ext;

			// Remove any current data for this phash:
		$this->removeOldIndexedFiles($hash['phash']);

			// Split filename:
		$fileParts = parse_url($file);

			// Setting new
		$fields = array(
			'phash' => $hash['phash'],
			'phash_grouping' => $hash['phash_grouping'],
			'cHashParams' => serialize($subinfo),
			'contentHash' => $content_md5h,
			'data_filename' => $file,
			'item_type' => $storeItemType,
			'item_title' => trim($contentParts['title']) ? $contentParts['title'] : basename($file),
			'item_description' => $this->bodyDescription($contentParts),
			'item_mtime' => $mtime,
			'item_size' => $size,
			'item_crdate' => $ctime,
			'tstamp' => time(),
			'crdate' => time(),
			'gr_list' => $this->conf['gr_list'],
 			'externalUrl' => $fileParts['scheme'] ? 1 : 0,
 			'recordUid' => intval($this->conf['recordUid']),
 			'freeIndexUid' => intval($this->conf['freeIndexUid']),
 			'freeIndexSetId' => intval($this->conf['freeIndexSetId']),
		);
		
						
		$elements = array();
		$elements['type'] = $storeItemType;
		$elements['ref'] = $file;
		$elements['title'] = $fields['item_title'];
		$elements['description'] = $this->bodyDescription($contentParts);
		$elements['text'] = $this->bodyDescription($contentParts);
		$elements['keywords'] = '';		
		$elements['abstract'] = '';			
		$elements['crdate'] = $fields['crdate'];
					
		
		$index = $this->createIndex();
		$doc = $this->createDocument($elements);

				
		$this->deleteFromLuceneIndex($index,$fields['data_page_id']);
		$index->addDocument($doc);	
			
		$GLOBALS['TYPO3_DB']->exec_INSERTquery('index_phash', $fields);

			// PROCESSING index_fulltext
		$fields = array(
			'phash' => $hash['phash'],
			'fulltextdata' => implode(' ', $contentParts)
		);
		if ($this->indexerConfig['fullTextDataLength']>0)	{
			$fields['fulltextdata'] = substr($fields['fulltextdata'],0,$this->indexerConfig['fullTextDataLength']);
		}
		$GLOBALS['TYPO3_DB']->exec_INSERTquery('index_fulltext', $fields);

			// PROCESSING index_debug
		if ($this->indexerConfig['debugMode'])	{
			$fields = array(
				'phash' => $hash['phash'],
				'debuginfo' => serialize(array(
						'cHashParams' => $subinfo,
						'contentParts' => array_merge($contentParts,array('body' => substr($contentParts['body'],0,1000))),
						'logs' => $this->internal_log,
						'lexer' => $this->lexerObj->debugString,
					))
			);
			$GLOBALS['TYPO3_DB']->exec_INSERTquery('index_debug', $fields);
		}
	}
	
	
	/* --------- Lucene specific function -------------- */
	
	/**
	 * create generic lucene document containing the fields type, ref, title, extract,
	 * text, crdate (time integer)
	 *
	 * @param array the element-values to store
	 * @return Zend_Search_Lucene_Document Lucene Document
	 */

	protected function createDocument($elements)
	{
        /*
         * 	Feldtyp 	Gespeichert 	Indiziert 	In Token aufgeteilt 	Binär
			Keyword 	Ja 				Ja 			Nein 					Nein
			UnIndexed 	Ja 				Nein 		Nein 					Nein
			Binary 		Ja 				Nein 		Nein 					Ja
			Text 		Ja 				Ja 			Ja 						Nein
			UnStored 	Nein 			Ja 			Ja 						Nein
         */		
		
		$doc = new Zend_Search_Lucene_Document();
		$doc->addField(Zend_Search_Lucene_Field::Keyword('type', $elements['type'], 'UTF-8'));
		$doc->addField(Zend_Search_Lucene_Field::Keyword('ref', $elements['ref'], 'UTF-8'));
		$doc->addField(Zend_Search_Lucene_Field::Text('title', $elements['title'], 'UTF-8'));
		$doc->addField(Zend_Search_Lucene_Field::UnIndexed('extract', $elements['extract'], 'UTF-8'));
		$doc->addField(Zend_Search_Lucene_Field::Text('bodytext', $elements['text'], 'UTF-8'));
		$doc->addField(Zend_Search_Lucene_Field::Keyword('crdate', $elements['crdate'], 'UTF-8'));
		$doc->addField(Zend_Search_Lucene_Field::Text('keywords', $elements['keywords'], 'UTF-8'));
		$doc->addField(Zend_Search_Lucene_Field::Text('abstract', $elements['abstract'], 'UTF-8'));
				
    	return $doc;
	}
	
	
	/**
	 *  create or update the lucene index
	 *
	 *  @param array $extConf configuration array
	 */

	public function createIndex()
	{
		$timeBegin = time();
		
		// TODO: make the path configurable
		$indexPath = '/typo3temp/lucene_search_index';		
		
		$index = $this->getIndex(PATH_site . $indexPath);

 		if ($this->debug) {
	 		echo "MergeBuffer " . $index->getMaxBufferedDocs() . "\n";
			echo "MergeFactor " . $index->getMergeFactor() . "\n";
			echo "start: " . $this->getMemUsage() ." M\n";
 		}
		
 		if ($this->indexOptimize) {
 			if ($extConf['debug']) {
 				echo "optimize ... \n";
 			}
 			$index->optimize();
 		}

		$index->commit();
            
		// $this->swapPath(PATH_site . $indexPath);
		
		$duration = time() - $timeBegin;
		echo "INDEX size: " . $index->count() . " docs: " . $index->numDocs() . " time: $duration sec\n";

		if ($this->debug) {          
			echo "mem-peak: " . $this->getMemUsage()  . "M / " . ini_get('memory_limit') . "\n";
		}
		
		return $index;
	}
	
	/**
	 *  open the lucene indexdir
	 *  if not exists -> create the index
	 *  hint: sets Utf8Num_CaseInsensitive parser and UTF-8 encoding!
	 *
	 * 	@return Zend_Search_Lucene_Interface the index object if sucessful
	 *  @throws Zend_Search_Lucene_Exception on index creation error (or permission problem)
	 */

	public function getIndex($indexDir)
	{
		try {
			$index = Zend_Search_Lucene::open($indexDir);
		} catch (Zend_Search_Lucene_Exception $e) {
			try {
				$index = Zend_Search_Lucene::create($indexDir);
			} catch (Zend_Search_Lucene_Exception $e) {
				throw new PowerSearchException( sprintf( 'Error creating index in "%1$s", reason: "%2$s"', $indexDir, $e->getMessage() ) );
			}
		}
		Zend_Search_Lucene_Storage_Directory_Filesystem::setDefaultFilePermissions(0664);

		$analyzer = new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive();
		Zend_Search_Lucene_Analysis_Analyzer::setDefault( $analyzer );
		Zend_Search_Lucene_Search_QueryParser::setDefaultEncoding('UTF-8');

		$index->setMaxBufferedDocs(1);  // minimize memory consumption

		return $index;
	}
	
	
	private function swapPath($path) 
	{
    	chmod( $path . '.new', 0775 );

		@rename( $path, $path . '.old' );
		if( is_dir( $path ) === false ) {
			if( rename( $path . '.new', $path ) !== false ) {
				$this->rmPath( $path . '.old' );
			}
		}			
	}
	
	/**
	 * Deletes files and directories
	 *
	 * @param string $location Path with directories and files which should be deleted
	 */

	public static function rmPath( $path )
	{
		if( is_dir( $path ) === true )
		{
			if( ( $handle = opendir( $path ) ) !== false )
			{
				while( ( $name = readdir( $handle ) ) )
				{
					if( $name == '.' || $name == '..' ) { continue; }
					self::rmPath( $path . DIRECTORY_SEPARATOR . $name );
				}
				closedir( $handle );
			}
			rmdir( $path );
		}
		else
		{
			unlink( $path );
		}
	}
	
	
	
	private function getMemUsage() 
	{
		if (function_exists('memory_get_peak_usage')) {
		    $memory = memory_get_peak_usage(true);
		} else {
		    $memory = memory_get_usage(true);
		}							
		return ($memory / 1024 / 1024);
	}
	
	
	private function deleteFromLuceneIndex($index, $refId)
	{
		// delete old index entries with the actual ref id
		$query = new Zend_Search_Lucene_Search_Query_Boolean();
		$refIDTerm = new Zend_Search_Lucene_Index_Term($refId, 'ref');
		$refIDQuery = new Zend_Search_Lucene_Search_Query_Term($refIDTerm);
		$query->addSubquery($refIDQuery, true /* required */);
		$hits = $index->find($query);	
		if(is_array($hits) && count($hits)>0)
		{
			foreach($hits as $hit)
			{
				$index->delete($hit->id);
			}
		}		
	}
	
	
	
	
	
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/indexed_search/class.indexer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/indexed_search/class.indexer.php']);
}
?>