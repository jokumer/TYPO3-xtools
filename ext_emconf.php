<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TYPO3 toolbox',
    'description' => 'TYPO3 toolbox for system support',
    'category' => 'misc',
    'author' => 'J.Kummer',
    'author_email' => 'typo3 et enobe dot de',
    'author_company' => 'enobe.de',
    'shy' => '',
    'priority' => '',
    'module' => '',
    'state' => 'beta',
    'internal' => '',
    'uploadfolder' => 0,
    'createDirs' => '',
    'modify_tables' => '',
    'clearCacheOnLoad' => 0,
    'lockType' => '',
    'version' => '1.1.0-dev',
    'constraints' => [
        'depends' => [
            'typo3' => '7.6.0-7.6.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'Jokumer\\Xtools\\' => 'Classes',
        ],
    ],
];