<?php
/**
 * BE-module
 */
if (TYPO3_MODE === 'BE') {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'Jokumer.' . $_EXTKEY,
        'tools',
        'Toolbox',
        '',
        array(
            'Toolbox' => 'index',
            'FileAndFolderPermission' => 'index',
            'FileDuplication' => 'index,showDuplications',
        ),
        array(
            'access' => 'user,group',
            'icon' => 'EXT:' . $_EXTKEY . '/ext_icon.svg',
            'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_module.xlf',
        )
    );
}