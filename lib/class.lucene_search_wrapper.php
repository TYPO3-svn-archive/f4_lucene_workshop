<?php
/**
 * Plugin 'tx_f4luceneworkshop_ui' for the 'f4_lucene_workshop' extension.
 * 
 * a wrapper class for the lucene search
 */

require_once 'Zend/Search/Lucene.php';

class lucene_search_wrapper {	
	
	private $query = false;
	private $index = false;
		
	function __construct($indexDirectory)
	{
		$this->index = Zend_Search_Lucene::open($indexDirectory);
	}	
	
	function setQuery($searchTerm,$useQueryParser=true)
	{		
		$this->query = new Zend_Search_Lucene_Search_Query_Boolean();
		$userQuery = Zend_Search_Lucene_Search_QueryParser::parse($searchTerm);					

		// this code shows how you could add another parameter to the search query
		/* 
		 	$titleTerm = new Zend_Search_Lucene_Index_Term('johanniter', 'title');
			$titleQuery = new Zend_Search_Lucene_Search_Query_Term($titleTerm);
			$this->query->addSubquery($titleQuery, true);
		*/
		$this->query->addSubquery($userQuery, true);		
	}
	
	function find()
	{			
		$hits = $this->index->find($this->query);				
		return $hits;
	}
	
	function highlightMatches($text)
	{
		return $this->query->highlightMatches($text);
	}
	
	function getQueryTerms()
	{
		return $this->query->getQueryTerms();
	}
	
	
}


?>