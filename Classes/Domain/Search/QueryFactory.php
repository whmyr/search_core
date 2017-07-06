<?php
namespace Leonmrni\SearchCore\Domain\Search;

/*
 * Copyright (C) 2017  Daniel Siepmann <coding@daniel-siepmann.de>
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

use Leonmrni\SearchCore\Connection\ConnectionInterface;
use Leonmrni\SearchCore\Connection\Elasticsearch\Query;
use Leonmrni\SearchCore\Connection\SearchRequestInterface;
use TYPO3\CMS\Extbase\Utility\ArrayUtility;

class QueryFactory
{
    /**
     * @var \TYPO3\CMS\Core\Log\Logger
     */
    protected $logger;

    /**
     * @var array
     */
    protected $query = [];

    /**
     * @param \TYPO3\CMS\Core\Log\LogManager $logManager
     */
    public function __construct(\TYPO3\CMS\Core\Log\LogManager $logManager)
    {
        $this->logger = $logManager->getLogger(__CLASS__);
    }

    /**
     * @param SearchRequestInterface $searchRequest
     *
     * @return \Elastica\Query
     */
    public function create(SearchRequestInterface $searchRequest)
    {
        return $this->createElasticaQuery($searchRequest);
    }

    /**
     * @param SearchRequestInterface $searchRequest
     *
     * TODO: This is not in scope Elasticsearch, therefore should not return elastica.
     * @return \Elastica\Query
     */
    protected function createElasticaQuery(SearchRequestInterface $searchRequest)
    {
        $this->addSearch($searchRequest);
        $this->addFilter($searchRequest);
        $this->addFacets($searchRequest);

        // TODO: Add logging here.
        $this->logger->debug('Generated elasticsearch query.', [$this->query]);
        return new \Elastica\Query($this->query);
    }

    /**
     * @param SearchRequestInterface $searchRequest
     */
    protected function addSearch(SearchRequestInterface $searchRequest)
    {
        $this->query = ArrayUtility::arrayMergeRecursiveOverrule($this->query, [
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'match' => [
                                '_all' => $searchRequest->getSearchTerm()
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param SearchRequestInterface $searchRequest
     */
    protected function addFilter(SearchRequestInterface $searchRequest)
    {
        if (! $searchRequest->hasFilter()) {
            return;
        }

        $terms = [];
        foreach ($searchRequest->getFilter() as $name => $value) {
            $terms[] = [
                'term' => [
                    $name => $value,
                ],
            ];
        }

        $this->query = ArrayUtility::arrayMergeRecursiveOverrule($this->query, [
            'query' => [
                'bool' => [
                    'filter' => $terms,
                ],
            ],
        ]);
    }

    /**
     * @param SearchRequestInterface $searchRequest
     */
    protected function addFacets(SearchRequestInterface $searchRequest)
    {
        foreach ($searchRequest->getFacets() as $facet) {
            $this->query = ArrayUtility::arrayMergeRecursiveOverrule($this->query, [
                'aggs' => [
                    $facet->getIdentifier() => [
                        'terms' => [
                            'field' => $facet->getField(),
                        ],
                    ],
                ],
            ]);
        }
    }
}
