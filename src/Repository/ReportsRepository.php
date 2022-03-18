<?php

namespace App\Repository;

use MongoDB\Client;
use MongoDB\Database;
use MongoDB\Driver\Cursor;

class ReportsRepository
{
    private Database $client;

    public function __construct()
    {
        $this->client = (new Client)->selectDatabase("demo-db");
    }

    public function hasActiveAccounts() : bool {
        return $this->client->selectCollection("accounts")
                ->findOne(['status'=>'ACTIVE']) != null;
    }

    public function findAccountsWithMetricsSummary(?string $accountId = null): Cursor
    {
        $accountsCollection = $this->client->selectCollection("accounts");

        $accounts = $accountsCollection->aggregate([
            [
                // Get only active documents, and filter by accountId if not null
                '$match' => ['status' => 'ACTIVE']
                    + ($accountId != null ?
                        ['accountId' => $accountId] : []),
            ],
            [
                // Find metrics for each account and add them up
                '$lookup' => [
                    'from' => 'metrics',
                    'localField' => 'accountId',
                    'foreignField' => 'accountId',
                    'as' => 'metrics',
                    'pipeline' => [
                        [
                            '$group' => [
                                '_id' => null,
                                'spend' => ['$sum' => '$spend'],
                                'impressions' => ['$sum' => '$impressions'],
                                'clicks' => ['$sum' => '$clicks']
                            ]
                        ],
                    ],

                ],
            ],
            // Merge metrics results into the root document
            [
                '$replaceRoot' => [
                    'newRoot' => [
                        '$mergeObjects' => [
                            // Initialize default metrics to zero
                            [
                                'spend' => 0,
                                'impressions' => 0,
                                'clicks' => 0,
                                'costPerClick' => 0
                            ],
                            ['$arrayElemAt' => ['$metrics', 0]],
                            '$$ROOT'
                        ]
                    ]
                ]
            ],
            // Metrics are merged into the document already, so
            // remove the unnecessary metrics array
            ['$project' => ['metrics' => 0]],
            // Calculate the 'costPerClick' metric and add it to the document
            [
                '$addFields' => [
                    'costPerClick' => [
                        // Check if 'clicks' is not zero so to avoid dividing by zero
                        '$cond' => [
                            ['$eq' => ['$clicks', 0]],
                            0,
                            ['$divide' => ['$spend', '$clicks']]
                        ]
                    ]
                ]
            ],
            // Sort documents by spend, descending
            [
                '$sort' => [
                    'spend' => -1,
                ],
            ],
        ]);

        return $accounts;
    }
}