<?php

namespace Codappix\SearchCore\Domain\Index\TcaIndexer;

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

interface TcaTableServiceInterface
{
    /**
     * @return string
     */
    public function getTableName(): string;

    /**
     * @return string
     */
    public function getTableClause(): string;

    /**
     * Filter the given records by root line blacklist settings.
     * @param array $records
     * @return void
     */
    public function filterRecordsByRootLineBlacklist(array &$records);

    /**
     * @param array $record
     * @return mixed
     */
    public function prepareRecord(array &$record);

    /**
     * @param string $columnName
     * @return array
     */
    public function getColumnConfig(string $columnName): array;

    /**
     * @param integer $offset
     * @param integer $limit
     * @return array
     */
    public function getRecords(int $offset, int $limit): array;

    /**
     * @param integer $identifier
     * @return array
     */
    public function getRecord(int $identifier): array;

    /**
     * @return string
     */
    public function getLanguageUidColumn(): string;
}
