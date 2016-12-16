<?php
namespace Leonmrni\SearchCore\Domain\Index\TcaIndexer;

/*
 * Copyright (C) 2016  Daniel Siepmann <coding@daniel-siepmann.de>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301, USA.
 */

use Leonmrni\SearchCore\Configuration\ConfigurationContainerInterface;
use Leonmrni\SearchCore\Domain\Index\IndexingException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Encapsulate logik related to TCA configuration.
 */
class TcaTableService
{
    /**
     * TCA for current table.
     * !REFERENCE! To save memory.
     * @var array
     */
    protected $tca;

    /**
     * @var string
     */
    protected $tableName;

    /**
     * @var ConfigurationContaineInterfacer
     */
    protected $configuration;

    /**
     * @var \TYPO3\CMS\Core\Log\Logger
     */
    protected $logger;

    /**
     * @var RelationResolver
     */
    protected $relationResolver;

    /**
     * Inject log manager to get concrete logger from it.
     *
     * @param \TYPO3\CMS\Core\Log\LogManager $logManager
     */
    public function injectLogger(\TYPO3\CMS\Core\Log\LogManager $logManager)
    {
        $this->logger = $logManager->getLogger(__CLASS__);
    }

    /**
     * @param string $tableName
     * @param ConfigurationContainer $configuration
     */
    public function __construct(
        $tableName,
        RelationResolver $relationResolver,
        ConfigurationContainerInterface $configuration
    ) {
        if (!isset($GLOBALS['TCA'][$tableName])) {
            throw new IndexingException(
                'Table "' . $tableName . '" is not configured in TCA.',
                IndexingException::CODE_UNKOWN_TCA_TABLE
            );
        }

        $this->tableName = $tableName;
        $this->tca = &$GLOBALS['TCA'][$this->tableName];
        $this->configuration = $configuration;
        $this->relationResolver = $relationResolver;
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * @return string
     */
    public function getTableClause()
    {
        return $this->tableName . ' LEFT JOIN pages on ' . $this->tableName . '.pid = pages.uid';
    }

    /**
     * Filter the given records by root line blacklist settings.
     *
     * @param array &$records
     * @return void
     */
    public function filterRecordsByRootLineBlacklist(array &$records)
    {
        $records = array_filter(
            $records,
            function ($record) {
                return ! $this->isRecordBlacklistedByRootline($record);
            }
        );
    }

    /**
     * Adjust record accordingly to configuration.
     * @param array &$record
     */
    public function prepareRecord(array &$record)
    {
        $this->relationResolver->resolveRelationsForRecord($this, $record);

        if (isset($record['uid']) && !isset($record['search_identifier'])) {
            $record['search_identifier'] = $record['uid'];
        }
        if (isset($record[$this->tca['ctrl']['label']]) && !isset($record['search_title'])) {
            $record['search_title'] = $record[$this->tca['ctrl']['label']];
        }
    }

    /**
     * @return string
     */
    public function getWhereClause()
    {
        $whereClause = '1=1'
            . BackendUtility::BEenableFields($this->tableName)
            . BackendUtility::deleteClause($this->tableName)

            . BackendUtility::BEenableFields('pages')
            . BackendUtility::deleteClause('pages')
            . ' AND pages.no_search = 0'
            ;

        $userDefinedWhere = $this->configuration->getIfExists('index', $this->tableName);
        if (is_string($userDefinedWhere)) {
            $whereClause .= $userDefinedWhere;
        }

        if ($this->isBlacklistedRootLineConfigured()) {
            $whereClause .= ' AND pages.uid NOT IN ('
                . implode(',', $this->getBlacklistedRootLine())
                . ')'
                . ' AND pages.pid NOT IN ('
                . implode(',', $this->getBlacklistedRootLine())
                . ')';
        }

        $this->logger->debug('Generated where clause.', [$this->tableName, $whereClause]);
        return $whereClause;
    }

    /**
     * @return string
     */
    public function getFields()
    {
        $fields = array_merge(
            ['uid','pid'],
            array_filter(
                array_keys($this->tca['columns']),
                function ($columnName) {
                    return !$this->isSystemField($columnName);
                }
            )
        );

        foreach ($fields as $key => $field) {
            $fields[$key] = $this->tableName . '.' . $field;
        }

        $this->logger->debug('Generated fields.', [$this->tableName, $fields]);
        return implode(',', $fields);
    }

    /**
     * @param string
     * @return bool
     */
    protected function isSystemField($columnName)
    {
        $systemFields = [
            // Versioning fields,
            // https://docs.typo3.org/typo3cms/TCAReference/Reference/Ctrl/Index.html#versioningws
            't3ver_oid', 't3ver_id', 't3ver_label', 't3ver_wsid',
            't3ver_state', 't3ver_stage', 't3ver_count', 't3ver_tstamp',
            't3ver_move_id', 't3ver_swapmode',
            $this->tca['ctrl']['transOrigDiffSourceField'],
            $this->tca['ctrl']['cruser_id'],
            $this->tca['ctrl']['fe_cruser_id'],
            $this->tca['ctrl']['fe_crgroup_id'],
            $this->tca['ctrl']['languageField'],
            $this->tca['ctrl']['origUid'],
        ];

        return in_array($columnName, $systemFields);
    }

    /**
     * @param string $columnName
     * @return array
     * @throws InvalidArgumentException
     */
    public function getColumnConfig($columnName)
    {
        if (!isset($this->tca['columns'][$columnName])) {
            throw new InvalidArgumentException(
                'Column does not exist.',
                InvalidArgumentException::COLUMN_DOES_NOT_EXIST
            );
        }

        return $this->tca['columns'][$columnName]['config'];
    }

    /**
     * Checks whether the given record was blacklisted by root line.
     * This can be configured by typoscript as whole root lines can be black listed.
     *
     * @param array &$record
     * @return bool
     */
    protected function isRecordBlacklistedByRootline(array &$record)
    {
        // NOTE: Does not support pages yet. We have to add a switch once we
        // support them to use uid instead.
        if (! $this->isBlackListedRootLineConfigured()) {
            return false;
        }

        foreach (BackendUtility::BEgetRootLine($record['uid']) as $pageInRootLine) {
            if (in_array($pageInRootLine['uid'], $this->getBlackListedRootLine())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether any page uids are black listed.
     *
     * @return bool
     */
    protected function isBlackListedRootLineConfigured()
    {
        return (bool) $this->configuration->getIfExists('index', 'rootLineBlacklist');
    }

    /**
     * Get the list of black listed root line page uids.
     *
     * @return array<Int>
     */
    protected function getBlackListedRootLine()
    {
        return GeneralUtility::intExplode(',', $this->configuration->getIfExists('index', 'rootLineBlacklist'));
    }
}