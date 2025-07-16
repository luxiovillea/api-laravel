<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Client;
use Google\Service\AnalyticsData;
use Google\Service\AnalyticsData\CohortSpec;
use Google\Service\AnalyticsData\DateRange as GoogleDateRange;
use Google\Service\AnalyticsData\Dimension;
use Google\Service\AnalyticsData\Metric;
use Google\Service\AnalyticsData\RunReportRequest;
use Google\Service\AnalyticsData\OrderBy;
use Google\Service\AnalyticsData\MetricOrderBy;
use Google\Service\AnalyticsData\RunRealtimeReportRequest;
use Exception;
use Carbon\Carbon;

/**
 * Controller komprehensif untuk Google Analytics 4 Data API.
 * Versi ini menarik hampir semua laporan utama dari dashboard GA4 dengan benar.
 */
class AnalyticsController extends Controller
{
    /**
     * Menginisialisasi Google Client.
     */
    private function getGoogleClient(): Client
    {
        $credentialsJson = env('GOOGLE_CREDENTIALS_JSON');
        if (empty($credentialsJson)) {
            throw new Exception("Environment variable GOOGLE_CREDENTIALS_JSON tidak di-set atau kosong.");
        }
        $client = new Client();
        $client->setAuthConfig(json_decode($credentialsJson, true));
        $client->addScope('https://www.googleapis.com/auth/analytics.readonly');
        return $client;
    }

    /**
     * Mengambil data REALTIME yang lebih lengkap.
     */
    public function fetchRealtimeData()
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');

            // --- Laporan Realtime yang valid ---
            $usersByPage = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['unifiedScreenName', 'deviceCategory'], ['activeUsers']);
            $usersByLocation = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['country', 'city'], ['activeUsers']);
            $usersByPlatform = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['platform'], ['activeUsers']);
            $usersByAudience = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['audienceName'], ['activeUsers']);
            $eventsRealtime = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['eventName'], ['eventCount']);
            $conversionsRealtime = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['eventName'], ['conversions']);
            $activityFeed = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['minutesAgo', 'unifiedScreenName', 'city'], ['activeUsers']);
            
            // DIHAPUS: Laporan sumber lalu lintas realtime karena dimensinya tidak valid untuk Realtime API.
            // $usersByTrafficSource = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['source', 'medium'], ['activeUsers']);

            // Hitung total pengguna dari laporan yang paling akurat
            $totalActiveUsers = $this->getRealtimeTotalUsers($analyticsData, $propertyId);

            return response()->json([
                'totalActiveUsers' => $totalActiveUsers,
                'reports' => [
                    'byPage'      => $usersByPage['rows'] ?? [],
                    'byLocation'  => $usersByLocation['rows'] ?? [],
                    'byPlatform'  => $usersByPlatform['rows'] ?? [],
                    'byAudience'  => $usersByAudience['rows'] ?? [],
                    'events'      => $eventsRealtime['rows'] ?? [],
                    'conversions' => $conversionsRealtime['rows'] ?? [],
                    'activityFeed'=> $activityFeed['rows'] ?? [],
                ]
            ]);

        } catch (Exception $e) {
            return $this->handleApiException('Realtime', $e);
        }
    }

    /**
     * Mengambil data HISTORIS yang jauh lebih lengkap.
     */
    public function fetchHistoricalData(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');

            $allowedPeriods = ['7days' => '7daysAgo', '28days' => '28daysAgo', '90days' => '90daysAgo'];
            $period = $request->query('period', '28days');
            $startDate = $allowedPeriods[$period] ?? $allowedPeriods['28days'];
            $dateRange = ['start_date' => $startDate, 'end_date' => 'today'];

            // === Laporan Historis Utama ===
            
            // Laporan Akuisisi Lalu Lintas (Sesi) - Ini adalah laporan yang benar untuk "sumber lalu lintas"
            $trafficSourceData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['sessionSourceMedium'], ['sessions', 'activeUsers', 'newUsers', 'engagementRate', 'conversions'], 'sessions');

            // Laporan Detail Halaman (sesuai gambar pertama Anda)
            $detailedPageReport = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['pageTitle'], ['screenPageViews', 'activeUsers', 'screenPageViewsPerUser', 'averageEngagementTime', 'eventCount', 'conversions', 'totalRevenue'], 'screenPageViews', 10);
            
            // Laporan Tren Harian untuk Grafik
            $dailyTrendData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['date'], ['activeUsers', 'sessions'], null, 100);

            // Laporan Halaman Landing
            $landingPageData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['landingPage'], ['sessions', 'newUsers', 'engagementRate'], 'sessions');

            // Laporan Demografi & Geografi
            $geoData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['country', 'city'], ['activeUsers', 'newUsers', 'sessions'], 'activeUsers');
            $demographicsData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['gender', 'age'], ['activeUsers', 'sessions'], 'activeUsers');

            // Laporan Teknologi (Device, Browser, OS)
            $techData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['deviceCategory', 'browser', 'operatingSystem'], ['sessions', 'activeUsers'], 'sessions');

            // Laporan Semua Event
            $allEventsData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['eventName'], ['eventCount', 'totalUsers', 'eventCountPerUser'], 'eventCount');
            
            // --- Laporan Cohort tidak diubah ---
            $retentionData = $this->runCohortReport($analyticsData, $propertyId);

            // --- Summary ---
            $summaryTotals = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, [], ['activeUsers', 'newUsers', 'sessions', 'conversions', 'screenPageViews', 'engagementRate', 'averageSessionDuration'])['totals'];
            $summary = [
                'activeUsers'     => (int) ($summaryTotals['activeUsers'] ?? 0),
                'newUsers'        => (int) ($summaryTotals['newUsers'] ?? 0),
                'sessions'        => (int) ($summaryTotals['sessions'] ?? 0),
                'conversions'     => (int) ($summaryTotals['conversions'] ?? 0),
                'screenPageViews' => (int) ($summaryTotals['screenPageViews'] ?? 0),
                'engagementRate'  => round((float)($summaryTotals['engagementRate'] ?? 0) * 100, 2) . '%',
                'averageSessionDuration' => gmdate("i\m s\s", (int)($summaryTotals['averageSessionDuration'] ?? 0)),
            ];

            return response()->json([
                'summary'   => $summary,
                'reports' => [
                    // Laporan disusun berdasarkan kategori agar mudah dibaca di frontend
                    'overview' => [
                        'dailyTrends'       => $dailyTrendData['rows'] ?? [],
                    ],
                    'engagement' => [
                        'detailedPageReport'=> $detailedPageReport['rows'] ?? [],
                        'landingPages'      => $landingPageData['rows'] ?? [],
                        'allEvents'         => $allEventsData['rows'] ?? [],
                    ],
                    'user' => [
                        'demographics'      => $demographicsData['rows'] ?? [],
                        'geography'         => $geoData['rows'] ?? [],
                        'technology'        => $techData['rows'] ?? [],
                    ],
                    'acquisition' => [
                        'trafficSources'    => $trafficSourceData['rows'] ?? [],
                    ],
                    'retention' => [
                        'userRetention'     => $retentionData ?? [],
                    ],
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleApiException('Historis', $e);
        }
    }
    
    // --- HELPER FUNCTIONS (TIDAK ADA PERUBAHAN, SUDAH BENAR) ---

    private function getRealtimeTotalUsers($analyticsData, $propertyId): int
    {
        $response = $this->runRealtimeReportHelper($analyticsData, $propertyId, [], ['activeUsers']);
        return $response['rows'][0]['activeUsers'] ?? 0;
    }

    private function runRealtimeReportHelper($analyticsData, $propertyId, array $dimensions, array $metrics): array
    {
        $dimensionObjects = array_map(fn($name) => new Dimension(['name' => $name]), $dimensions);
        $metricObjects = array_map(fn($name) => new Metric(['name' => $name]), $metrics);
        
        $request = new RunRealtimeReportRequest([
            'dimensions' => $dimensionObjects, 
            'metrics' => $metricObjects, 
            'limit' => 250,
            'metricAggregations' => ['TOTAL'] 
        ]);

        if (!empty($metrics)) {
             $request->setOrderBys([new OrderBy(['metric' => new MetricOrderBy(['metric_name' => $metrics[0]]), 'desc' => true])]);
        }

        $response = $analyticsData->properties->runRealtimeReport("properties/{$propertyId}", $request);
        $result = ['rows' => []];
        foreach ($response->getRows() as $row) {
            $rowData = [];
            foreach ($row->getDimensionValues() as $i => $dimValue) { $rowData[$dimensions[$i]] = $dimValue->getValue(); }
            foreach ($row->getMetricValues() as $i => $metricValue) { $rowData[$metrics[$i]] = is_numeric($metricValue->getValue()) ? (float)$metricValue->getValue() : $metricValue->getValue(); }
            $result['rows'][] = $rowData;
        }
        return $result;
    }

    private function runHistoricalReport($analyticsData, $propertyId, array $dateRangeConfig, array $dimensions, array $metrics, ?string $orderByMetric = null, int $limit = 25): array
    {
        $dimensionObjects = array_map(fn($name) => new Dimension(['name' => $name]), $dimensions);
        $metricObjects = array_map(fn($name) => new Metric(['name' => $name]), $metrics);
        
        $requestConfig = [
            'dateRanges' => [new GoogleDateRange($dateRangeConfig)],
            'dimensions' => $dimensionObjects,
            'metrics' => $metricObjects,
            'limit' => $limit,
            'metricAggregations' => ['TOTAL']
        ];

        if ($orderByMetric) { 
            $requestConfig['orderBys'] = [new OrderBy(['metric' => new MetricOrderBy(['metric_name' => $orderByMetric]), 'desc' => true])]; 
        }

        $request = new RunReportRequest($requestConfig);
        $response = $analyticsData->properties->runReport('properties/' . $propertyId, $request);
        
        $result = ['rows' => [], 'totals' => []];
        foreach ($response->getRows() as $row) {
            $rowData = [];
            foreach ($row->getDimensionValues() as $i => $dimValue) {
                if ($dimensions[$i] === 'date') {
                    $rowData[$dimensions[$i]] = Carbon::createFromFormat('Ymd', $dimValue->getValue())->format('Y-m-d');
                } else {
                    $rowData[$dimensions[$i]] = $dimValue->getValue();
                }
            }
            foreach ($row->getMetricValues() as $i => $metricValue) { 
                $rowData[$metrics[$i]] = is_numeric($metricValue->getValue()) ? (float)$metricValue->getValue() : $metricValue->getValue();
            }
            $result['rows'][] = $rowData;
        }

        if ($response->getTotals() && count($response->getTotals()) > 0) {
            $totalsRow = $response->getTotals()[0];
            foreach ($totalsRow->getMetricValues() as $i => $metricValue) { 
                $result['totals'][$metrics[$i]] = is_numeric($metricValue->getValue()) ? (float)$metricValue->getValue() : $metricValue->getValue();
            }
        }
        
        if ($orderByMetric === null && !empty($result['rows']) && isset($result['rows'][0]['date'])) {
            usort($result['rows'], fn($a, $b) => strcmp($a['date'], $b['date']));
        }
        return $result;
    }

    private function runCohortReport($analyticsData, $propertyId): array
    {
        // Fungsi ini tidak perlu diubah, sudah sangat baik.
        $cohortSpec = new CohortSpec(['cohorts' => [ new \Google\Service\AnalyticsData\Cohort(['name' => 'cohort_week_0', 'dimension' => 'firstTouchDate', 'dateRange' => new GoogleDateRange(['start_date' => '7daysAgo', 'end_date' => 'today'])]), new \Google\Service\AnalyticsData\Cohort(['name' => 'cohort_week_1', 'dimension' => 'firstTouchDate', 'dateRange' => new GoogleDateRange(['start_date' => '14daysAgo', 'end_date' => '8daysAgo'])]), new \Google\Service\AnalyticsData\Cohort(['name' => 'cohort_week_2', 'dimension' => 'firstTouchDate', 'dateRange' => new GoogleDateRange(['start_date' => '21daysAgo', 'end_date' => '15daysAgo'])]), new \Google\Service\AnalyticsData\Cohort(['name' => 'cohort_week_3', 'dimension' => 'firstTouchDate', 'dateRange' => new GoogleDateRange(['start_date' => '28daysAgo', 'end_date' => '22daysAgo'])]), ], 'cohortsRange' => new \Google\Service\AnalyticsData\CohortsRange(['granularity' => 'WEEKLY', 'start_offset' => 0, 'end_offset' => 4]), 'cohortReportSettings' => new \Google\Service\AnalyticsData\CohortReportSettings(['accumulate' => false]), ]);
        $request = new RunReportRequest(['cohortSpec' => $cohortSpec, 'dimensions' => [new Dimension(['name' => 'cohort']), new Dimension(['name' => 'cohortNthWeek'])], 'metrics' => [new Metric(['name' => 'cohortActiveUsers'])], ]);
        $response = $analyticsData->properties->runReport('properties/' . $propertyId, $request);
        $retentionTable = [];
        foreach ($response->getRows() as $row) {
            $cohortName = $row->getDimensionValues()[0]->getValue();
            $weekNumber = (int) $row->getDimensionValues()[1]->getValue();
            $activeUsers = (int) $row->getMetricValues()[0]->getValue();
            if (!isset($retentionTable[$cohortName])) { $retentionTable[$cohortName] = ['cohort_date_range' => '', 'users_per_week' => [], 'total_users' => 0]; }
            if ($weekNumber === 0) { $retentionTable[$cohortName]['total_users'] = $activeUsers; }
            $retentionTable[$cohortName]['users_per_week'][$weekNumber] = $activeUsers;
        }
        $formattedOutput = [];
        foreach($retentionTable as $name => $data) {
            $totalUsers = $data['total_users'];
            if ($totalUsers === 0) continue;
            $weeklyRetention = [];
            foreach($data['users_per_week'] as $week => $users) { $weeklyRetention["Week " . $week] = [ 'users' => $users, 'percentage' => round(($users / $totalUsers) * 100, 2) ]; }
            $dateRange = collect($cohortSpec->getCohorts())->firstWhere('name', $name)->getDateRange();
            $startDate = Carbon::parse($dateRange->getStartDate())->format('d M');
            $endDate = Carbon::parse($dateRange->getEndDate())->format('d M');
            $formattedOutput[] = [ 'cohort' => "Users from: {$startDate} - {$endDate}", 'total_initial_users' => $totalUsers, 'retention' => $weeklyRetention, ];
        }
        return $formattedOutput;
    }

    private function handleApiException(string $context, Exception $e): \Illuminate\Http\JsonResponse
    {
        $message = $e->getMessage();
        $decoded = json_decode($message, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['error']['message'])) {
            $message = $decoded['error']['message'];
        }
        return response()->json(['error' => "Gagal mengambil data {$context}: " . $message], 500);
    }
}