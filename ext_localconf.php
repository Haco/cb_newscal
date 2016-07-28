<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

$GLOBALS['TYPO3_CONF_VARS']['EXT']['news']['switchableControllerActions']['newItems']['News->calendar'] = 'LLL:EXT:cb_newscal/Resources/Private/Language/locallang_db.xlf:flexforms_general.mode.newscal_calendar';


// Display custom information for Preview in Page module
$GLOBALS['TYPO3_CONF_VARS']['EXT']['news']['GeorgRinger\\News\\Hooks\\PageLayoutView']['extensionSummary']['cb_newscal'] = 'Cbrunet\CbNewscal\Hooks\PageLayoutView->getExtensionSummary';

// Update fields in the flexform
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['getFlexFormDSClass'][] = \Cbrunet\CbNewscal\Hooks\BackendUtility::class;

$GLOBALS['TYPO3_CONF_VARS']['EXT']['news']['classes']['Controller/NewsController'][] = 'cb_newscal';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['cb_newscal_events'] = 'Cbrunet\CbNewscal\Updates\RoqNewsevent';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['cb_newscal_plugin'] = 'Cbrunet\CbNewscal\Updates\PluginUpdate';
