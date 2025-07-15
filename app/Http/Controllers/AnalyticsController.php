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
use Google\Service\AnalyticsData\FilterExpression;
use Google\Service\AnalyticsData\FilterExpressionList;
use Google\Service\AnalyticsData\Filter;
use Google\Service\AnalyticsData\Filter\StringFilter;
use Exception;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
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
     * Mengambil semua data historis lengkap.
     * Fungsi ini dipanggil oleh rute /api/analytics-data.
     */
    public function fetchHistoricalData(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);

            // Mengambil ID dari variabel environment di Railway
            $propertyId = env('GA_PROPERTY_ID');
            if (empty($propertyId)) {
                throw new Exception("Environment variable GA_PROPERTY_ID tidak di-set atau kosong di Railway.");
            }

            // Mengambil periode dari parameter URL (misal: ?period=7days), default 28 hari
            $allowedPeriods = ['7days' => '7daysAgo', '28days' => '28daysAgo', '90days' => '90daysAgo'];
            $period = $request->query('period', '28days');
            $startDate = $allowedPeriods[$period] ?? $allowedPeriods['28days'];
            $dateRange = ['start_date' => $startDate, 'end_date' => 'today'];

            $filterExpression = $this->buildFilterExpression($request);
            
            // Mengambil semua laporan yang dibutuhkan
            $pagesAndScreensData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['unifiedScreenName'], ['screenPageViews', 'activeUsers', 'averageSessionDuration', 'eventCount', 'conversions', 'totalRevenue'], 'screenPageViews', 25, $filterExpression);
            $geoData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['country', 'city'], ['activeUsers', 'newUsers', 'sessions', 'engagementRate'], 'activeUsers', 25, $filterExpression);
            $dailyTrendData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['date'], ['activeUsers', 'sessions'], null, 100, $filterExpression);
            $trafficSourceData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['sessionSourceMedium'], ['sessions', 'activeUsers', 'newUsers'], 'sessions', 25, $filterExpression);
            $landingPageData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['landingPage'], ['sessions', 'newUsers', 'engagementRate'], 'sessions', 25, $filterExpression);
            $techData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['deviceCategory', 'browser', 'operatingSystem'], ['sessions', 'activeUsers'], 'sessions', 25, $filterExpression);
            $conversionEventData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['eventName'], ['conversions'], 'conversions', 25, $filterExpression);
            $retentionData = $this->runCohortReport($analyticsData, $propertyId);

            // Kalkulasi manual untuk "Views per User"
            foreach ($pagesAndScreensData['rows'] as &$row) {
                $views = (float)($row['screenPageViews'] ?? 0);
                $users = (float)($row['activeUsers'] ?? 0);
                $row['viewsPerUser'] = $users > 0 ? round($views / $users, 2) : 0;
            }
            unset($row);
            if (!empty($pagesAndScreensData['totals'])) {
                $totalViews = (float)($pagesAndScreensData['totals']['screenPageViews'] ?? 0);
                $totalUsers = (float)($pagesAndScreensData['totals']['activeUsers'] ?? 0);
                $pagesAndScreensData['totals']['viewsPerUser'] = $totalUsers > 0 ? round($totalViews / $totalUsers, 2) : 0;
            }
            
            // Membuat Ringkasan (Summary) dari total laporan
            $summary = [
                'activeUsers'     => (int) ($geoData['totals']['activeUsers'] ?? 0),
                'newUsers'        => (int) ($geoData['totals']['newUsers'] ?? 0),
                'sessions'        => (int) ($geoData['totals']['sessions'] ?? 0),
                'conversions'     => (int) ($pagesAndScreensData['totals']['conversions'] ?? 0),
                'screenPageViews' => (int) ($pagesAndScreensData['totals']['screenPageViews'] ?? 0),
                'engagementRate'  => round((float)($geoData['totals']['engagementRate'] ?? 0) * 100, 2) . '%',
                'averageSessionDuration' => gmdate("i\m s\s", (int)($pagesAndScreensData['totals']['averageSessionDuration'] ?? 0)),
            ];

            return response()->json([
                'metadata' => [ 'propertyId' => $propertyId, 'period' => $period, 'filtersApplied' => $request->only(['country', 'city', 'pageTitle', 'sourceMedium'])],
                'summary' => $summary,
                'reports' => [
                    'pagesAndScreens'   => $pagesAndScreensData,
                    'dailyTrends'       => $dailyTrendData['rows'] ?? [],
                    'geography'         => $geoData,
                    'trafficSources'    => $trafficSourceData,
                    'landingPages'      => $landingPageData,
                    'technology'        => $techData,
                    'conversionEvents'  => $conversionEventData,
                    'userRetention'     => $retentionData ?? [],
                ]
            ]);

        } catch (Exception $e) {
            return $this->handleApiException("Historis", $e);
        }
    }
    
    // --- FUNGSI HELPER YANG DIBUTUHKAN ---

    private function buildFilterExpression(Request $request): ?FilterExpression
    {
        $filters = [];
        $supportedFilters = [
            'country' => 'country', 'city' => 'city', 'pageTitle' => 'pageTitle', 'sourceMedium' => 'sessionSourceMedium'
        ];
        foreach ($supportedFilters as $queryParam => $dimensionName) {
            if ($request->has($queryParam)) {
                $filters[] = new Filter(['field_name' => $dimensionName, 'string_filter' => new StringFilter(['match_type' => 'CONTAINS', 'value' => $request->query($queryParam), 'case_sensitive' => false])]);
            }
        }
        if (empty($filters)) { return null; }
        return new FilterExpression(['and_group' => new FilterExpressionList(['expressions' => $filters])]);
    }

    private function runHistoricalReport($analyticsData, $propertyId, array $dateRangeConfig, array $dimensions, array $metrics, ?string $orderByMetric = null, int $limit = 25, ?FilterExpression $filterExpression = null): array
    {
        $dimensionObjects = array_map(fn($name) => new Dimension(['name' => $name]), $dimensions);
        $metricObjects = array_map(fn($name) => new Metric(['name' => $name]), $metrics);
        $requestConfig = [
            'dateRanges' => [new GoogleDateRange($dateRangeConfig)], 'dimensions' => $dimensionObjects, 'metrics' => $metricObjects, 'limit' => $limit, 'metricAggregations' => ['TOTAL'] 
        ];
        if ($orderByMetric) {
            $requestConfig['orderBys'] = [new OrderBy(['metric' => new MetricOrderBy(['metric_name' => $orderByMetric]), 'desc' => true])];
        }
        if ($filterExpression) {
            $requestConfig['dimensionFilter'] = $filterExpression;
        }
        $request = new RunReportRequest($requestConfig);
        $response = $analyticsData->properties->runReport('properties/' . $propertyId, $request);
        
        $result = ['rows' => [], 'totals' => []];
        foreach ($response->getRows() as $row) {
            $rowData = [];
            foreach ($row->getDimensionValues() as $i => $dimValue) {
                $dimName = $dimensions[$i];
                $rowData[$dimName] = ($dimName === 'date') ? Carbon::createFromFormat('Ymd', $dimValue->getValue())->format('Y-m-d') : $dimValue->getValue();
            }
            foreach ($row->getMetricValues() as $i => $metricValue) { $rowData[$metrics[$i]] = $metricValue->getValue(); }
            $result['rows'][] = $rowData;
        }
        if ($response->getTotals() && count($response->getTotals()) > 0) {
            $totalsRow = $response->getTotals()[0];
            foreach ($totalsRow->getMetricValues() as $i => $metricValue) {
                $result['totals'][$metrics[$i]] = $metricValue->getValue();
            }
        }
        if ($orderByMetric === null && !empty($result['rows']) && isset($result['rows'][0]['date'])) {
            usort($result['rows'], fn($a, $b) => $a['date'] <=> $b['date']);
        }
        return $result;
    }

    private function runCohortReport($analyticsData, $propertyId): array
    {
        $cohortSpec = new CohortSpec([
            'cohorts' => [
                new \Google\Service\AnalyticsData\Cohort(['name' => 'cohort_week_0', 'dimension' => 'firstTouchDate', 'dateRange' => new GoogleDateRange(['start_date' => '7daysAgo', 'end_date' => 'today'])]),
                new \Google\Service\AnalyticsData\Cohort(['name' => 'cohort_week_1', 'dimension' => 'firstTouchDate', 'dateRange' => new GoogleDateRange(['start_date' => '14daysAgo', 'end_date' => '8daysAgo'])]),
            ],
            'cohortsRange' => new \Google\Service\AnalyticsData\CohortsRange(['granularity' => 'WEEKLY', 'start_offset' => 0, 'end_offset' => 4]),
            'cohortReportSettings' => new \Google\Service\AnalyticsData\CohortReportSettings(['accumulate' => false]),
        ]);
        $request = new RunReportRequest(['cohortSpec' => $cohortSpec, 'dimensions' => [new Dimension(['name' => 'cohort']), new Dimension(['name' => 'cohortNthWeek'])], 'metrics' => [new Metric(['name' => 'cohortActiveUsers'])], ]);
        $response = $analyticsData->properties->runReport('properties/' . $propertyId, $request);
        $retentionTable = [];
        foreach ($response->getRows() as $row) {
            $cohortName = $row->getDimensionValues()[0]->getValue();
            $weekNumber = (int) $row->getDimensionValues()[1]->getValue();
            $activeUsers = (int) $row->getMetricValues()[0]->getValue();
            if (!isset($retentionTable[$cohortName])) { $retentionTable[$cohortName] = ['users_per_week' => [], 'total_users' => 0]; }
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