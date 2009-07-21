<?php

if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$confArray = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['f4_lucene_workshop']);

# =========================================================================== #
# PLUGINS
# =========================================================================== #

// Lucene Workshop
t3lib_extMgm::addPlugin(Array('LLL:EXT:f4_lucene_workshop/locallang.xml:ui_title', $_EXTKEY.'_ui'),'list_type');
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_ui']='layout,select_key,recursive';
if (TYPO3_MODE=="BE") $TBE_MODULES_EXT["xMOD_db_new_content_el"]["addElClasses"]["tx_f4luceneworkshop_ui_wizicon"] = t3lib_extMgm::extPath($_EXTKEY).'ui/class.tx_f4luceneworkshop_ui_wizicon.php';	

?>