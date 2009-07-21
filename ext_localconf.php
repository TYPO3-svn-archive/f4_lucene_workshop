<?php

if (!defined ('TYPO3_MODE')) die ('Access denied.');


# =========================================================================== #
#  HOOKS
# =========================================================================== #


# =========================================================================== #
#  XCLASS
#  INDEXED SEARCH
# =========================================================================== #
$TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/indexed_search/class.indexer.php'] = t3lib_extMgm::extPath($_EXTKEY).'xclass/class.ux_indexer.php';

# =========================================================================== #
#  PIs
# =========================================================================== #

t3lib_extMgm::addPItoST43($_EXTKEY,'ui/class.tx_f4luceneworkshop_ui.php','_ui','list_type',1);


# =========================================================================== #
#  CLI Cron
# =========================================================================== #

if (TYPO3_MODE == 'BE') {
#	$TYPO3_CONF_VARS['SC_OPTIONS']['GLOBAL']['cliKeys'][$_EXTKEY.'_travelmed'] = array('EXT:'.$_EXTKEY.'/travelmed/cli/class.tx_spmsd_travelmed_cli.php','_CLI_spmsd');
}

?>