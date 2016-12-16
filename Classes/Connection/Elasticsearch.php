<?php
namespace Leonmrni\SearchCore\Connection;

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

use TYPO3\CMS\Core\SingletonInterface as Singleton;

/**
 * Outer wrapper to elasticsearch.
 */
class Elasticsearch implements Singleton, ConnectionInterface
{
    /**
     * @var Elasticsearch\Connection
     */
    protected $connection;

    /**
     * @var Elasticsearch\IndexFactory
     */
    protected $indexFactory;

    /**
     * @var Elasticsearch\TypeFactory
     */
    protected $typeFactory;

    /**
     * @var Elasticsearch\DocumentFactory
     */
    protected $documentFactory;

    /**
     * @var \TYPO3\CMS\Core\Log\Logger
     */
    protected $logger;

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
     * @param Elasticsearch\Connection $connection
     * @param Elasticsearch\IndexFactory $indexFactory
     * @param Elasticsearch\TypeFactory $typeFactory
     * @param Elasticsearch\DocumentFactory $documentFactory
     */
    public function __construct(
        Elasticsearch\Connection $connection,
        Elasticsearch\IndexFactory $indexFactory,
        Elasticsearch\TypeFactory $typeFactory,
        Elasticsearch\DocumentFactory $documentFactory
    ) {
        $this->connection = $connection;
        $this->indexFactory = $indexFactory;
        $this->typeFactory = $typeFactory;
        $this->documentFactory = $documentFactory;
    }

    public function addDocument($documentType, array $document)
    {
        $this->withType(
            $documentType,
            function ($type) use ($document) {
                $type->addDocument($this->documentFactory->getDocument($type->getName(), $document));
            }
        );
    }

    public function deleteDocument($documentType, $identifier)
    {
        $this->withType(
            $documentType,
            function ($type) use ($identifier) {
                $type->deleteById($identifier);
            }
        );
    }

    public function updateDocument($documentType, array $document)
    {
        $this->withType(
            $documentType,
            function ($type) use ($document) {
                $type->updateDocument($this->documentFactory->getDocument($type->getName(), $document));
            }
        );
    }

    public function addDocuments($documentType, array $documents)
    {
        $this->withType(
            $documentType,
            function ($type) use ($documents) {
                $type->addDocuments($this->documentFactory->getDocuments($type->getName(), $documents));
            }
        );
    }

    /**
     * Execute given callback with Elastica Type based on provided documentType
     *
     * @param string $documentType
     * @param callable $callback
     */
    protected function withType($documentType, callable $callback)
    {
        $type = $this->getType($documentType);
        $callback($type);
        $type->getIndex()->refresh();
    }

    /**
     * @param SearchRequestInterface $searchRequest
     *
     * @return \Elastica\ResultSet
     */
    public function search(SearchRequestInterface $searchRequest)
    {
        $this->logger->debug('Search for', [$searchRequest->getSearchTerm()]);

        $search = new \Elastica\Search($this->connection->getClient());
        $search->addIndex('typo3content');

        // TODO: Return wrapped result to implement our interface.
        // Also update php doc to reflect the change.
        return $search->search('"' . $searchRequest->getSearchTerm() . '"');
    }

    /**
     * @param string $documentType
     *
     * @return \Elastica\Type
     */
    protected function getType($documentType)
    {
        return $this->typeFactory->getType(
            $this->indexFactory->getIndex(
                $this->connection,
                $documentType
            ),
            $documentType
        );
    }
}