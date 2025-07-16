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
use Google\Service\AnalyticsData\Filter;
use Google\Service\AnalyticsData\FilterExpression;
use Google\Service\AnalyticsData\StringFilter;
use Google\Service\AnalyticsData\InListFilter;
use Google\Service\AnalyticsData\NumericFilter;
use Google\Service\AnalyticsData\DimensionFilter;
use Google\Service\AnalyticsData\MetricFilter;
use Exception;
use Carbon\Carbon;

/**
 * Controller komprehensif untuk Google Analytics 4 Data API.
 * Versi ini menarik hampir semua laporan utama dari dashboard GA4 dengan benar.
 * Enhanced dengan filter dropdown yang lengkap.
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
     * Mendapatkan semua opsi filter yang tersedia untuk dropdown
     */
    public function getFilterOptions()
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');
            
            // Periode untuk mengambil data filter (30 hari terakhir)
            $dateRange = ['start_date' => '30daysAgo', 'end_date' => 'today'];
            
            // Ambil semua opsi filter yang tersedia
            $countries = $this->getFilterOptionValues($analyticsData, $propertyId, $dateRange, 'country');
            $cities = $this->getFilterOptionValues($analyticsData, $propertyId, $dateRange, 'city');
            $deviceCategories = $this->getFilterOptionValues($analyticsData, $propertyId, $dateRange, 'deviceCategory');
            $browsers = $this->getFilterOptionValues($analyticsData, $propertyId, $dateRange, 'browser');
            $operatingSystems = $this->getFilterOptionValues($analyticsData, $propertyId, $dateRange, 'operatingSystem');
            $trafficSources = $this->getFilterOptionValues($analyticsData, $propertyId, $dateRange, 'sessionSourceMedium');
            $landingPages = $this->getFilterOptionValues($analyticsData, $propertyId, $dateRange, 'landingPage');
            $pageLocations = $this->getFilterOptionValues($analyticsData, $propertyId, $dateRange, 'pageLocation');
            $pageTitles = $this->getFilterOptionValues($analyticsData, $propertyId, $dateRange, 'pageTitle');
            $eventNames = $this->getFilterOptionValues($analyticsData, $propertyId, $dateRange, 'eventName');
            $ageGroups = $this->getFilterOptionValues($analyticsData, $propertyId, $dateRange, 'age');
            $genders = $this->getFilterOptionValues($analyticsData, $propertyId, $dateRange, 'gender');
            $languages = $this->getFilterOptionValues($analyticsData, $propertyId, $dateRange, 'language');
            $screenResolutions = $this->getFilterOptionValues($analyticsData, $propertyId, $dateRange, 'screenResolution');
            
            // Predefined date ranges
            $dateRanges = [
                ['label' => 'Hari ini', 'value' => 'today'],
                ['label' => 'Kemarin', 'value' => 'yesterday'],
                ['label' => 'Minggu ini', 'value' => 'thisWeek'],
                ['label' => 'Minggu lalu', 'value' => 'lastWeek'],
                ['label' => '7 hari terakhir', 'value' => '7daysAgo'],
                ['label' => '14 hari terakhir', 'value' => '14daysAgo'],
                ['label' => '28 hari terakhir', 'value' => '28daysAgo'],
                ['label' => '30 hari terakhir', 'value' => '30daysAgo'],
                ['label' => '60 hari terakhir', 'value' => '60daysAgo'],
                ['label' => '90 hari terakhir', 'value' => '90daysAgo'],
                ['label' => 'Bulan ini', 'value' => 'thisMonth'],
                ['label' => 'Bulan lalu', 'value' => 'lastMonth'],
                ['label' => 'Tahun ini', 'value' => 'thisYear'],
                ['label' => 'Tahun lalu', 'value' => 'lastYear'],
                ['label' => 'Kustom', 'value' => 'custom']
            ];
            
            return response()->json([
                'dateRanges' => $dateRanges,
                'geography' => [
                    'countries' => $countries,
                    'cities' => $cities
                ],
                'technology' => [
                    'deviceCategories' => $deviceCategories,
                    'browsers' => $browsers,
                    'operatingSystems' => $operatingSystems,
                    'screenResolutions' => $screenResolutions
                ],
                'acquisition' => [
                    'trafficSources' => $trafficSources
                ],
                'engagement' => [
                    'landingPages' => $landingPages,
                    'pageLocations' => $pageLocations,
                    'pageTitles' => $pageTitles,
                    'eventNames' => $eventNames
                ],
                'demographics' => [
                    'ageGroups' => $ageGroups,
                    'genders' => $genders,
                    'languages' => $languages
                ]
            ]);
            
        } catch (Exception $e) {
            return $this->handleApiException('Filter Options', $e);
        }
    }

    /**
     * Mengambil data REALTIME yang lebih lengkap dengan filter.
     */
    public function fetchRealtimeData(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');
            
            // Parse filters dari request
            $filters = $this->parseFilters($request);

            // --- Laporan Realtime yang valid dengan filter ---
            $usersByPage = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['unifiedScreenName', 'deviceCategory'], ['activeUsers'], $filters);
            $usersByLocation = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['country', 'city'], ['activeUsers'], $filters);
            $usersByPlatform = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['platform'], ['activeUsers'], $filters);
            $usersByAudience = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['audienceName'], ['activeUsers'], $filters);
            $eventsRealtime = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['eventName'], ['eventCount'], $filters);
            $conversionsRealtime = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['eventName'], ['conversions'], $filters);
            $activityFeed = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['minutesAgo', 'unifiedScreenName', 'city'], ['activeUsers'], $filters);
            
            // Hitung total pengguna dari laporan yang paling akurat
            $totalActiveUsers = $this->getRealtimeTotalUsers($analyticsData, $propertyId, $filters);

            return response()->json([
                'totalActiveUsers' => $totalActiveUsers,
                'appliedFilters' => $filters,
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
     * Mengambil data HISTORIS yang jauh lebih lengkap dengan filter.
     */
    public function fetchHistoricalData(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');

            // Parse date range
            $dateRange = $this->parseDateRange($request);
            
            // Parse filters dari request
            $filters = $this->parseFilters($request);

            // === Laporan Historis Utama dengan Filter ===
            
            // Laporan Akuisisi Lalu Lintas (Sesi)
            $trafficSourceData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['sessionSourceMedium'], ['sessions', 'activeUsers', 'newUsers', 'engagementRate', 'conversions'], 'sessions', 25, $filters);

            // Laporan Detail Halaman
            $detailedPageReport = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['pageTitle'], ['screenPageViews', 'activeUsers', 'screenPageViewsPerUser', 'averageEngagementTime', 'eventCount', 'conversions', 'totalRevenue'], 'screenPageViews', 10, $filters);
            
            // Laporan Tren Harian untuk Grafik
            $dailyTrendData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['date'], ['activeUsers', 'sessions'], null, 100, $filters);

            // Laporan Halaman Landing
            $landingPageData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['landingPage'], ['sessions', 'newUsers', 'engagementRate'], 'sessions', 25, $filters);

            // Laporan Demografi & Geografi
            $geoData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['country', 'city'], ['activeUsers', 'newUsers', 'sessions'], 'activeUsers', 25, $filters);
            $demographicsData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['gender', 'age'], ['activeUsers', 'sessions'], 'activeUsers', 25, $filters);

            // Laporan Teknologi (Device, Browser, OS)
            $techData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['deviceCategory', 'browser', 'operatingSystem'], ['sessions', 'activeUsers'], 'sessions', 25, $filters);

            // Laporan Semua Event
            $allEventsData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['eventName'], ['eventCount', 'totalUsers', 'eventCountPerUser'], 'eventCount', 25, $filters);
            
            // Laporan E-commerce (jika ada)
            $ecommerceData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['itemName'], ['purchaseRevenue', 'itemsViewed', 'itemsPurchased'], 'purchaseRevenue', 25, $filters);
            
            // --- Laporan Cohort ---
            $retentionData = $this->runCohortReport($analyticsData, $propertyId, $filters);

            // --- Summary ---
            $summaryTotals = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, [], ['activeUsers', 'newUsers', 'sessions', 'conversions', 'screenPageViews', 'engagementRate', 'averageSessionDuration', 'totalRevenue', 'bounceRate'], null, 1, $filters)['totals'];
            
            $summary = [
                'activeUsers'     => (int) ($summaryTotals['activeUsers'] ?? 0),
                'newUsers'        => (int) ($summaryTotals['newUsers'] ?? 0),
                'sessions'        => (int) ($summaryTotals['sessions'] ?? 0),
                'conversions'     => (int) ($summaryTotals['conversions'] ?? 0),
                'screenPageViews' => (int) ($summaryTotals['screenPageViews'] ?? 0),
                'engagementRate'  => round((float)($summaryTotals['engagementRate'] ?? 0) * 100, 2) . '%',
                'averageSessionDuration' => gmdate("i\m s\s", (int)($summaryTotals['averageSessionDuration'] ?? 0)),
                'totalRevenue'    => number_format((float)($summaryTotals['totalRevenue'] ?? 0), 2),
                'bounceRate'      => round((float)($summaryTotals['bounceRate'] ?? 0) * 100, 2) . '%',
            ];

            return response()->json([
                'summary'   => $summary,
                'appliedFilters' => $filters,
                'dateRange' => $dateRange,
                'reports' => [
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
                    'monetization' => [
                        'ecommerce'         => $ecommerceData['rows'] ?? [],
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

    /**
     * Endpoint khusus untuk mendapatkan data dengan filter tertentu
     */
    public function getFilteredReport(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');
            
            // Parse parameters
            $reportType = $request->get('report_type', 'custom');
            $dimensions = $request->get('dimensions', []);
            $metrics = $request->get('metrics', []);
            $dateRange = $this->parseDateRange($request);
            $filters = $this->parseFilters($request);
            $orderBy = $request->get('order_by');
            $limit = $request->get('limit', 25);
            
            // Validasi input
            if (empty($dimensions) || empty($metrics)) {
                return response()->json(['error' => 'Dimensions dan metrics harus diisi'], 400);
            }
            
            $reportData = $this->runHistoricalReport(
                $analyticsData, 
                $propertyId, 
                $dateRange, 
                $dimensions, 
                $metrics, 
                $orderBy, 
                $limit, 
                $filters
            );
            
            return response()->json([
                'reportType' => $reportType,
                'appliedFilters' => $filters,
                'dateRange' => $dateRange,
                'data' => $reportData
            ]);
            
        } catch (Exception $e) {
            return $this->handleApiException('Filtered Report', $e);
        }
    }

    // --- NEW FILTER HELPER FUNCTIONS ---

    /**
     * Parse filters dari request
     */
    private function parseFilters(Request $request): array
    {
        $filters = [];
        
        // Country filter
        if ($request->has('country') && !empty($request->get('country'))) {
            $filters['country'] = $request->get('country');
        }
        
        // City filter
        if ($request->has('city') && !empty($request->get('city'))) {
            $filters['city'] = $request->get('city');
        }
        
        // Device category filter
        if ($request->has('device_category') && !empty($request->get('device_category'))) {
            $filters['deviceCategory'] = $request->get('device_category');
        }
        
        // Browser filter
        if ($request->has('browser') && !empty($request->get('browser'))) {
            $filters['browser'] = $request->get('browser');
        }
        
        // Operating system filter
        if ($request->has('operating_system') && !empty($request->get('operating_system'))) {
            $filters['operatingSystem'] = $request->get('operating_system');
        }
        
        // Traffic source filter
        if ($request->has('traffic_source') && !empty($request->get('traffic_source'))) {
            $filters['sessionSourceMedium'] = $request->get('traffic_source');
        }
        
        // Landing page filter
        if ($request->has('landing_page') && !empty($request->get('landing_page'))) {
            $filters['landingPage'] = $request->get('landing_page');
        }
        
        // Page title filter
        if ($request->has('page_title') && !empty($request->get('page_title'))) {
            $filters['pageTitle'] = $request->get('page_title');
        }
        
        // Event name filter
        if ($request->has('event_name') && !empty($request->get('event_name'))) {
            $filters['eventName'] = $request->get('event_name');
        }
        
        // Age filter
        if ($request->has('age') && !empty($request->get('age'))) {
            $filters['age'] = $request->get('age');
        }
        
        // Gender filter
        if ($request->has('gender') && !empty($request->get('gender'))) {
            $filters['gender'] = $request->get('gender');
        }
        
        // Language filter
        if ($request->has('language') && !empty($request->get('language'))) {
            $filters['language'] = $request->get('language');
        }
        
        // Multiple values filter (array support)
        if ($request->has('filters') && is_array($request->get('filters'))) {
            $filters = array_merge($filters, $request->get('filters'));
        }
        
        return $filters;
    }

    /**
     * Parse date range dari request
     */
    private function parseDateRange(Request $request): array
    {
        $period = $request->get('period', '28daysAgo');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        
        // Jika custom date range
        if ($period === 'custom' && $startDate && $endDate) {
            return [
                'start_date' => $startDate,
                'end_date' => $endDate
            ];
        }
        
        // Predefined periods
        $allowedPeriods = [
            'today' => ['start_date' => 'today', 'end_date' => 'today'],
            'yesterday' => ['start_date' => 'yesterday', 'end_date' => 'yesterday'],
            'thisWeek' => ['start_date' => '7daysAgo', 'end_date' => 'today'],
            'lastWeek' => ['start_date' => '14daysAgo', 'end_date' => '8daysAgo'],
            '7daysAgo' => ['start_date' => '7daysAgo', 'end_date' => 'today'],
            '14daysAgo' => ['start_date' => '14daysAgo', 'end_date' => 'today'],
            '28daysAgo' => ['start_date' => '28daysAgo', 'end_date' => 'today'],
            '30daysAgo' => ['start_date' => '30daysAgo', 'end_date' => 'today'],
            '60daysAgo' => ['start_date' => '60daysAgo', 'end_date' => 'today'],
            '90daysAgo' => ['start_date' => '90daysAgo', 'end_date' => 'today'],
            'thisMonth' => ['start_date' => '30daysAgo', 'end_date' => 'today'],
            'lastMonth' => ['start_date' => '60daysAgo', 'end_date' => '31daysAgo'],
            'thisYear' => ['start_date' => '365daysAgo', 'end_date' => 'today'],
            'lastYear' => ['start_date' => '730daysAgo', 'end_date' => '366daysAgo'],
        ];
        
        return $allowedPeriods[$period] ?? $allowedPeriods['28daysAgo'];
    }

    /**
     * Mendapatkan opsi filter values untuk dropdown
     */
    private function getFilterOptionValues($analyticsData, $propertyId, array $dateRange, string $dimension, int $limit = 50): array
    {
        try {
            $result = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, [$dimension], ['activeUsers'], 'activeUsers', $limit);
            
            return array_map(function($row) use ($dimension) {
                return [
                    'label' => $row[$dimension] ?? '(not set)',
                    'value' => $row[$dimension] ?? '(not set)',
                    'users' => $row['activeUsers'] ?? 0
                ];
            }, $result['rows'] ?? []);
            
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Membuat filter expression untuk GA4 API
     */
    private function createFilterExpression(array $filters): ?FilterExpression
    {
        if (empty($filters)) {
            return null;
        }
        
        $filterExpressions = [];
        
        foreach ($filters as $dimension => $value) {
            if (is_array($value)) {
                // Multiple values (OR condition)
                $filterExpressions[] = new FilterExpression([
                    'filter' => new Filter([
                        'field_name' => $dimension,
                        'in_list_filter' => new InListFilter([
                            'values' => $value
                        ])
                    ])
                ]);
            } else {
                // Single value
                $filterExpressions[] = new FilterExpression([
                    'filter' => new Filter([
                        'field_name' => $dimension,
                        'string_filter' => new StringFilter([
                            'match_type' => 'EXACT',
                            'value' => $value
                        ])
                    ])
                ]);
            }
        }
        
        if (count($filterExpressions) === 1) {
            return $filterExpressions[0];
        } else {
            // Multiple filters (AND condition)
            return new FilterExpression([
                'and_group' => new FilterExpressionList([
                    'expressions' => $filterExpressions
                ])
            ]);
        }
    }
    
    // --- EXISTING HELPER FUNCTIONS (UPDATED WITH FILTER SUPPORT) ---

    private function getRealtimeTotalUsers($analyticsData, $propertyId, array $filters = []): int
    {
        $response = $this->runRealtimeReportHelper($analyticsData, $propertyId, [], ['activeUsers'], $filters);
        return $response['rows'][0]['activeUsers'] ?? 0;
    }

    private function runRealtimeReportHelper($analyticsData, $propertyId, array $dimensions, array $metrics, array $filters = []): array
    {
        $dimensionObjects = array_map(fn($name) => new Dimension(['name' => $name]), $dimensions);
        $metricObjects = array_map(fn($name) => new Metric(['name' => $name]), $metrics);
        
        $requestConfig = [
            'dimensions' => $dimensionObjects, 
            'metrics' => $metricObjects, 
            'limit' => 250,
            'metricAggregations' => ['TOTAL'] 
        ];
        
        // Add filters if provided
        $filterExpression = $this->createFilterExpression($filters);
        if ($filterExpression) {
            $requestConfig['dimension_filter'] = $filterExpression;
        }
        
        $request = new RunRealtimeReportRequest($requestConfig);

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

    private function runHistoricalReport($analyticsData, $propertyId, array $dateRangeConfig, array $dimensions, array $metrics, ?string $orderByMetric = null, int $limit = 25, array $filters = []): array
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
        
        // Add filters if provided
        $filterExpression = $this->createFilterExpression($filters);
        if ($filterExpression) {
            $requestConfig['dimension_filter'] = $filterExpression;
        }

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

    private function runCohortReport($analyticsData, $propertyId, array $filters = []): array
    {
        $cohortSpec = new CohortSpec(['cohorts' => [ 
            new \Google\Service\AnalyticsData\Cohort(['name' => 'cohort_week_0', 'dimension' => 'firstTouchDate', 'dateRange' => new GoogleDateRange(['start_date' => '7daysAgo', 'end_date' => 'today'])]), 
            new \Google\Service\AnalyticsData\Cohort(['name' => 'cohort_week_1', 'dimension' => 'firstTouchDate', 'dateRange' => new GoogleDateRange(['start_date' => '14daysAgo', 'end_date' => '8daysAgo'])]), 
            new \Google\Service\AnalyticsData\Cohort(['name' => 'cohort_week_2', 'dimension' => 'firstTouchDate', 'dateRange' => new GoogleDateRange(['start_date' => '21daysAgo', 'end_date' => '15daysAgo'])]), 
            new \Google\Service\AnalyticsData\Cohort(['name' => 'cohort_week_3', 'dimension' => 'firstTouchDate', 'dateRange' => new GoogleDateRange(['start_date' => '28daysAgo', 'end_date' => '22daysAgo'])]), 
        ], 'cohortsRange' => new \Google\Service\AnalyticsData\CohortsRange(['granularity' => 'WEEKLY', 'start_offset' => 0, 'end_offset' => 4]), 'cohortReportSettings' => new \Google\Service\AnalyticsData\CohortReportSettings(['accumulate' => false]), ]);
        
        $requestConfig = [
            'cohortSpec' => $cohortSpec, 
            'dimensions' => [new Dimension(['name' => 'cohort']), new Dimension(['name' => 'cohortNthWeek'])], 
            'metrics' => [new Metric(['name' => 'cohortActiveUsers'])], 
        ];
        
        // Add filters if provided
        $filterExpression = $this->createFilterExpression($filters);
        if ($filterExpression) {
            $requestConfig['dimension_filter'] = $filterExpression;
        }
        
        $request = new RunReportRequest($requestConfig);
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

    /**
     * Endpoint untuk mendapatkan data dengan multiple filters sekaligus
     */
    public function getMultiFilteredData(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');
            
            $dateRange = $this->parseDateRange($request);
            $filters = $this->parseFilters($request);
            
            // Ambil berbagai laporan sekaligus dengan filter yang sama
            $reports = [];
            
            // Pages report
            if ($request->get('include_pages', true)) {
                $reports['pages'] = $this->runHistoricalReport(
                    $analyticsData, $propertyId, $dateRange, 
                    ['pageTitle', 'pagePath'], 
                    ['screenPageViews', 'activeUsers', 'averageEngagementTime'], 
                    'screenPageViews', 10, $filters
                );
            }
            
            // Traffic sources report
            if ($request->get('include_traffic', true)) {
                $reports['traffic'] = $this->runHistoricalReport(
                    $analyticsData, $propertyId, $dateRange, 
                    ['sessionSource', 'sessionMedium'], 
                    ['sessions', 'activeUsers', 'engagementRate'], 
                    'sessions', 10, $filters
                );
            }
            
            // Geography report
            if ($request->get('include_geography', true)) {
                $reports['geography'] = $this->runHistoricalReport(
                    $analyticsData, $propertyId, $dateRange, 
                    ['country', 'city'], 
                    ['activeUsers', 'sessions', 'engagementRate'], 
                    'activeUsers', 10, $filters
                );
            }
            
            // Technology report
            if ($request->get('include_technology', true)) {
                $reports['technology'] = $this->runHistoricalReport(
                    $analyticsData, $propertyId, $dateRange, 
                    ['deviceCategory', 'browser'], 
                    ['sessions', 'activeUsers', 'engagementRate'], 
                    'sessions', 10, $filters
                );
            }
            
            // Events report
            if ($request->get('include_events', true)) {
                $reports['events'] = $this->runHistoricalReport(
                    $analyticsData, $propertyId, $dateRange, 
                    ['eventName'], 
                    ['eventCount', 'totalUsers', 'eventCountPerUser'], 
                    'eventCount', 10, $filters
                );
            }
            
            return response()->json([
                'appliedFilters' => $filters,
                'dateRange' => $dateRange,
                'reports' => $reports
            ]);
            
        } catch (Exception $e) {
            return $this->handleApiException('Multi-Filtered Data', $e);
        }
    }

    /**
     * Endpoint untuk mendapatkan data comparison (perbandingan periode)
     */
    public function getComparisonData(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');
            
            // Parse current period
            $currentDateRange = $this->parseDateRange($request);
            
            // Parse comparison period
            $comparisonPeriod = $request->get('comparison_period', 'previous_period');
            $comparisonDateRange = $this->getComparisonDateRange($currentDateRange, $comparisonPeriod);
            
            $filters = $this->parseFilters($request);
            $dimensions = $request->get('dimensions', ['date']);
            $metrics = $request->get('metrics', ['activeUsers', 'sessions']);
            
            // Get current period data
            $currentData = $this->runHistoricalReport(
                $analyticsData, $propertyId, $currentDateRange, 
                $dimensions, $metrics, null, 100, $filters
            );
            
            // Get comparison period data
            $comparisonData = $this->runHistoricalReport(
                $analyticsData, $propertyId, $comparisonDateRange, 
                $dimensions, $metrics, null, 100, $filters
            );
            
            // Calculate changes
            $changes = [];
            foreach ($metrics as $metric) {
                $currentTotal = $currentData['totals'][$metric] ?? 0;
                $comparisonTotal = $comparisonData['totals'][$metric] ?? 0;
                
                if ($comparisonTotal > 0) {
                    $change = (($currentTotal - $comparisonTotal) / $comparisonTotal) * 100;
                    $changes[$metric] = [
                        'current' => $currentTotal,
                        'comparison' => $comparisonTotal,
                        'change_percentage' => round($change, 2),
                        'change_absolute' => $currentTotal - $comparisonTotal
                    ];
                } else {
                    $changes[$metric] = [
                        'current' => $currentTotal,
                        'comparison' => 0,
                        'change_percentage' => $currentTotal > 0 ? 100 : 0,
                        'change_absolute' => $currentTotal
                    ];
                }
            }
            
            return response()->json([
                'appliedFilters' => $filters,
                'currentPeriod' => [
                    'dateRange' => $currentDateRange,
                    'data' => $currentData
                ],
                'comparisonPeriod' => [
                    'dateRange' => $comparisonDateRange,
                    'data' => $comparisonData
                ],
                'changes' => $changes
            ]);
            
        } catch (Exception $e) {
            return $this->handleApiException('Comparison Data', $e);
        }
    }

    /**
     * Helper untuk menghitung comparison date range
     */
    private function getComparisonDateRange(array $currentRange, string $comparisonPeriod): array
    {
        $startDate = Carbon::parse($currentRange['start_date']);
        $endDate = Carbon::parse($currentRange['end_date']);
        $daysDiff = $startDate->diffInDays($endDate);
        
        switch ($comparisonPeriod) {
            case 'previous_period':
                $comparisonStart = $startDate->copy()->subDays($daysDiff + 1);
                $comparisonEnd = $startDate->copy()->subDay();
                break;
            case 'previous_year':
                $comparisonStart = $startDate->copy()->subYear();
                $comparisonEnd = $endDate->copy()->subYear();
                break;
            case 'previous_month':
                $comparisonStart = $startDate->copy()->subMonth();
                $comparisonEnd = $endDate->copy()->subMonth();
                break;
            default:
                $comparisonStart = $startDate->copy()->subDays($daysDiff + 1);
                $comparisonEnd = $startDate->copy()->subDay();
        }
        
        return [
            'start_date' => $comparisonStart->format('Y-m-d'),
            'end_date' => $comparisonEnd->format('Y-m-d')
        ];
    }

    /**
     * Endpoint untuk mendapatkan data funnel analysis
     */
    public function getFunnelData(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');
            
            $dateRange = $this->parseDateRange($request);
            $filters = $this->parseFilters($request);
            
            // Define funnel steps (customize based on your needs)
            $funnelSteps = [
                'page_view' => ['eventName' => 'page_view'],
                'user_engagement' => ['eventName' => 'user_engagement'],
                'scroll' => ['eventName' => 'scroll'],
                'click' => ['eventName' => 'click'],
                'purchase' => ['eventName' => 'purchase']
            ];
            
            $funnelData = [];
            
            foreach ($funnelSteps as $stepName => $stepFilter) {
                $stepFilters = array_merge($filters, $stepFilter);
                
                $stepData = $this->runHistoricalReport(
                    $analyticsData, $propertyId, $dateRange, 
                    [], ['eventCount', 'totalUsers'], 
                    null, 1, $stepFilters
                );
                
                $funnelData[$stepName] = [
                    'events' => $stepData['totals']['eventCount'] ?? 0,
                    'users' => $stepData['totals']['totalUsers'] ?? 0
                ];
            }
            
            // Calculate conversion rates
            $firstStepUsers = $funnelData['page_view']['users'] ?? 1;
            foreach ($funnelData as $stepName => &$stepData) {
                $stepData['conversion_rate'] = round(($stepData['users'] / $firstStepUsers) * 100, 2);
            }
            
            return response()->json([
                'appliedFilters' => $filters,
                'dateRange' => $dateRange,
                'funnelData' => $funnelData
            ]);
            
        } catch (Exception $e) {
            return $this->handleApiException('Funnel Data', $e);
        }
    }

    /**
     * Endpoint untuk mendapatkan advanced segments
     */
    public function getSegmentedData(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');
            
            $dateRange = $this->parseDateRange($request);
            $baseFilters = $this->parseFilters($request);
            
            // Define segments
            $segments = [
                'new_users' => ['userType' => 'new'],
                'returning_users' => ['userType' => 'returning'],
                'mobile_users' => ['deviceCategory' => 'mobile'],
                'desktop_users' => ['deviceCategory' => 'desktop'],
                'tablet_users' => ['deviceCategory' => 'tablet'],
                'organic_users' => ['sessionMedium' => 'organic'],
                'paid_users' => ['sessionMedium' => 'cpc'],
                'direct_users' => ['sessionMedium' => '(none)']
            ];
            
            $segmentData = [];
            
            foreach ($segments as $segmentName => $segmentFilter) {
                $segmentFilters = array_merge($baseFilters, $segmentFilter);
                
                $data = $this->runHistoricalReport(
                    $analyticsData, $propertyId, $dateRange, 
                    [], ['activeUsers', 'sessions', 'engagementRate', 'averageSessionDuration'], 
                    null, 1, $segmentFilters
                );
                
                $segmentData[$segmentName] = [
                    'activeUsers' => $data['totals']['activeUsers'] ?? 0,
                    'sessions' => $data['totals']['sessions'] ?? 0,
                    'engagementRate' => round(($data['totals']['engagementRate'] ?? 0) * 100, 2),
                    'averageSessionDuration' => gmdate("i:s", $data['totals']['averageSessionDuration'] ?? 0)
                ];
            }
            
            return response()->json([
                'appliedFilters' => $baseFilters,
                'dateRange' => $dateRange,
                'segments' => $segmentData
            ]);
            
        } catch (Exception $e) {
            return $this->handleApiException('Segmented Data', $e);
        }
    }