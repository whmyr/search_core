<?php

call_user_func(function ($extension, $configuration) {
    if (is_string($configuration)) {
        $configuration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$extension]);
    }

    $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
    $iconRegistry->registerIcon(
        'plugin-' . $extension . '-form',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:search_core/Resources/Public/Icons/PluginForm.svg']
    );
    $iconRegistry->registerIcon(
        'plugin-' . $extension . '-results',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:search_core/Resources/Public/Icons/PluginResults.svg']
    );

    // TODO: Add hook for Extbase -> to handle records modified through
    // Frontend and backend modules not using datahandler
    \TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule(
        $GLOBALS['TYPO3_CONF_VARS'],
        [
            'SC_OPTIONS' => [
                'extbase' => [
                    'commandControllers' => [
                        $extension => Codappix\SearchCore\Command\IndexCommandController::class,
                    ],
                ],
                't3lib/class.t3lib_tcemain.php' => [
                    'clearCachePostProc' => [
                        $extension => \Codappix\SearchCore\Hook\DataHandler::class . '->clearCachePostProc',
                    ],
                    'processCmdmapClass' => [
                        $extension => \Codappix\SearchCore\Hook\DataHandler::class,
                    ],
                    'processDatamapClass' => [
                        $extension => \Codappix\SearchCore\Hook\DataHandler::class,
                    ],
                ],
            ],
        ]
    );

    TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'Codappix.' . $extension,
        'Results',
        ['Search' => 'results'],
        ['Search' => 'results']
    );

    TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'Codappix.' . $extension,
        'Form',
        ['Search' => 'form'],
        ['Search' => 'form']
    );

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
        '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:' . $extension . '/Configuration/TSconfig/Page/Mod/Wizards/NewContentElement.tsconfig">'
    );

    \Codappix\SearchCore\Compatibility\ImplementationRegistrationService::registerImplementations();

    if (empty($configuration) ||
        (isset($configuration['disable.']['elasticsearch']) &&
            filter_var($configuration['disable.']['elasticsearch'], FILTER_VALIDATE_BOOLEAN) === false)
    ) {
        \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\Container\Container::class)
            ->registerImplementation(
                \Codappix\SearchCore\Connection\ConnectionInterface::class,
                \Codappix\SearchCore\Connection\Elasticsearch::class
            );
    }
}, $_EXTKEY, $_EXTCONF);
