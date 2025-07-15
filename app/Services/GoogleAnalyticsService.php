<?php

namespace App\Services;

use Google_Client;
use Google_Service_AnalyticsData;

class GoogleAnalyticsService
{
    protected $analyticsData;

    public function __construct()
    {
        $client = new Google_Client();
        $client->setAuthConfig(storage_path('app/analytics/service-account.json'));
        $client->addScope('https://www.googleapis.com/auth/analytics.readonly');

        $this->analyticsData = new Google_Service_AnalyticsData($client);
    }

    public function getRealtimeUsers(string $propertyId)
    {
        $request = new \Google\Service\AnalyticsData\RunRealtimeReportRequest([
            'dimensions' => [
                ['name' => 'country'],
            ],
            'metrics' => [
                ['name' => 'activeUsers'],
            ],
        ]);

        return $this->analyticsData->properties->runRealtimeReport(
            'properties/' . $propertyId,
            $request
        );
    }
}
