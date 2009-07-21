<?php

class tx_f4luceneworkshop_ui_wizicon {
  
	/**
	 * Processing the wizard items array
	 *
	 * @param	array		$wizardItems: The wizard items
	 * @return	Modified array with wizard items
	 */
	function proc($wizardItems)	{
		global $LANG;

		$LL = $this->includeLocalLang();

		$wizardItems['plugins_f4luceneworkshop_ui'] = array(
			'icon'=>t3lib_extMgm::extRelPath('f4_lucene_workshop').'ui/ce_wiz.gif',
			'title'=>$LANG->getLLL('ui_title',$LL),
			'description'=>$LANG->getLLL('ui_plus_wiz_description',$LL),
			'params'=>'&defVals[tt_content][CType]=list&defVals[tt_content][list_type]=f4_lucene_workshop_ui'
		);

		return $wizardItems;
	}

	/**
	 * Reads the [extDir]/locallang.xml and returns the \$LOCAL_LANG array found in that file.
	 *
	 * @return	The array with language labels
	 */
	function includeLocalLang()	{
		global $LANG;
		$LOCAL_LANG = $LANG->includeLLFile('EXT:f4_lucene_workshop/locallang.xml',FALSE);
		return $LOCAL_LANG;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/f4_lucene_workshop/ui/class.tx_f4luceneworkshop_ui_wizicon.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/f4_lucene__workshop/ui/class.tx_f4luceneworkshop_ui_wizicon.php']);
}
?>