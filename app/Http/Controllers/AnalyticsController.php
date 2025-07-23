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
use Google\Service\AnalyticsData\FilterExpression;
use Google\Service\AnalyticsData\Filter;
use Google\Service\AnalyticsData\StringFilter;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Controller komprehensif untuk Google Analytics 4 Data API.
 */
class AnalyticsController extends Controller
{
    /**
     * Konfigurasi Aplikasi untuk metode baru yang fleksibel.
     */
    private $applications = [
        'mahasiswa' => [
            'name' => 'Aplikasi Mahasiswa',
            'page_path_filter' => '/mahasiswa', 
        ],
        'bytecafe' => [
            'name' => 'Byte Cafe',
            'page_path_filter' => '/bytecafe',
        ],
        'daftarbuku' => [
            'name' => 'Daftar Buku',
            'page_path_filter' => '/daftarbuku',
        ],
        'dataperalatan' => [
            'name' => 'Data Peralatan',
            'page_path_filter' => '/peralatan', 

        ],
    ];

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

    // ===================================================================
    // FUNGSI UTAMA 1: DASHBOARD SUMMARY (LAPORAN STANDAR/HISTORIS)
    // ===================================================================
    public function getDashboardSummary(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');
            $dateRange = $this->getDateRangeFromPeriod_new($request);
            $summaryData = [];
            $appIdCounter = 1;

            foreach ($this->applications as $appKey => $appConfig) {
                
                $appFilter = [
                    new FilterExpression(['filter' => new Filter([
                        'field_name' => 'pagePath',
                        'string_filter' => new StringFilter(['value' => $appConfig['page_path_filter'], 'match_type' => 'CONTAINS'])
                    ])])
                ];

                // PANGGILAN API 1: Metrik Utama
                $mainMetricsReport = $this->runAdvancedHistoricalReport($analyticsData, $propertyId, $dateRange, ['date'], ['sessions', 'activeUsers', 'newUsers', 'screenPageViews', 'engagementRate', 'averageSessionDuration', 'conversions'], null, $appFilter, 1);
                
                // PANGGILAN API 2: Geografis
                $geoReport = $this->runAdvancedHistoricalReport($analyticsData, $propertyId, $dateRange, ['country', 'city'], ['activeUsers'], 'activeUsers', $appFilter, 5);

                // PANGGILAN API 3: Sumber Trafik
                $trafficReport = $this->runAdvancedHistoricalReport($analyticsData, $propertyId, $dateRange, ['sessionSourceMedium'], ['sessions'], 'sessions', $appFilter, 1);

                // PANGGILAN API 4: Data Teknologi
                $techReport = $this->runAdvancedHistoricalReport($analyticsData, $propertyId, $dateRange, ['deviceCategory', 'browser', 'operatingSystem'], ['sessions', 'activeUsers'], 'sessions', $appFilter, 5);

                $totals = $mainMetricsReport['totals'] ?? [];
                
                $topCity = 'N/A';
                $topCountry = 'N/A';
                if (!empty($geoReport['rows'])) {
                    foreach ($geoReport['rows'] as $geoRow) {
                        if (!empty($geoRow['city']) && $geoRow['city'] !== '(not set)') {
                            $topCity = $geoRow['city'];
                            $topCountry = $geoRow['country'] ?? 'N/A';
                            break;
                        }
                    }
                }

                $topTrafficChannel = !empty($trafficReport['rows'][0]['sessionSourceMedium']) ? $trafficReport['rows'][0]['sessionSourceMedium'] : 'direct';

                $formattedTechData = [];
                if (!empty($techReport['rows'])) {
                    foreach ($techReport['rows'] as $techRow) {
                        $formattedTechData[] = [
                            'deviceCategory' => $techRow['deviceCategory'] ?? 'unknown',
                            'browser' => $techRow['browser'] ?? 'unknown',
                            'operatingSystem' => $techRow['operatingSystem'] ?? 'unknown',
                            'sessions' => (int)($techRow['sessions'] ?? 0),
                            'activeUsers' => (int)($techRow['activeUsers'] ?? 0),
                        ];
                    }
                }

                $summaryData[] = [
                    'id' => $appIdCounter++,
                    'app_key' => $appKey,
                    'name' => $appConfig['name'],
                    'key_metrics' => [
                        'total_visitor' => (int)($totals['sessions'] ?? 0),
                        'active_user' => (int)($totals['activeUsers'] ?? 0),
                        'new_user' => (int)($totals['newUsers'] ?? 0),
                        'page_views' => (int)($totals['screenPageViews'] ?? 0),
                    ],
                    'engagement' => [
                        'engagement_rate' => round(((float)($totals['engagementRate'] ?? 0)) * 100, 2) . '%',
                        'average_session_duration' => $this->formatDuration((float)($totals['averageSessionDuration'] ?? 0)),
                    ],
                    'top_sources' => [
                        'geography' => [ 'city' => $topCity, 'country' => $topCountry, ],
                        'traffic_channel' => $topTrafficChannel,
                    ],
                    'business' => [ 'conversions' => (int)($totals['conversions'] ?? 0), ],
                    'technology_overview' => $formattedTechData,
                ];
            }

            $finalResponse = [
                'data' => [
                    'applications' => $summaryData,
                    'meta' => [ 'total' => count($summaryData), 'page' => 1, 'limit' => count($summaryData), ],
                ],
                'metadata' => [ 'period' => $request->query('period', 'last_7_days'), 'dateRange' => $dateRange ]
            ];

            return response()->json($finalResponse);

        } catch (Exception $e) {
            return $this->handleApiException("Ringkasan Dashboard", $e);
        }
    }

    // ===================================================================
    // PENAMBAHAN BARU: FUNGSI UNTUK DATA REALTIME PER APLIKASI
    // ===================================================================

    /**
     * Mengambil data realtime (pengguna aktif saat ini) untuk setiap aplikasi.
     * Dipanggil oleh route: /analytics/realtime-summary
     */
    public function getRealtimeSummary()
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');
            $realtimeData = [];

            foreach ($this->applications as $appKey => $appConfig) {
                
                $realtimeFilter = new FilterExpression([
                    'filter' => new Filter([
                        'field_name' => 'unifiedScreenName',
                        'string_filter' => new StringFilter(['value' => $appConfig['page_path_filter'], 'match_type' => 'CONTAINS'])
                    ])
                ]);

                $report = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['unifiedScreenName'], ['activeUsers'], $realtimeFilter);
                
                $totalActiveUsers = collect($report['rows'] ?? [])->sum('activeUsers');

                $realtimeData[] = [
                    'app_key' => $appKey,
                    'name' => $appConfig['name'],
                    'active_users_now' => (int)$totalActiveUsers,
                ];
            }

            return response()->json(['data' => $realtimeData]);

        } catch (Exception $e) {
            return $this->handleApiException("Ringkasan Realtime", $e);
        }
    }

    // ===================================================================
    // FUNGSI LAPORAN DETAIL
    // ===================================================================
    
    public function generateReport(Request $request, string $appKey)
    {
        try {
            if (!isset($this->applications[$appKey])) {
                return response()->json(['error' => "Aplikasi '{$appKey}' tidak ditemukan."], 404);
            }
            $appConfig = $this->applications[$appKey];
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');
            $reportType = $request->query('type', 'pages');
            $dateRangeConfig = $this->getDateRangeFromPeriod_new($request);
            $filters = $this->buildAdvancedFilters($request, $appConfig);
            
            switch ($reportType) {
                case 'pages':
                    $dimensions = ['pageTitle', 'pagePath'];
                    $metrics = ['activeUsers', 'screenPageViews', 'averageSessionDuration', 'eventCount', 'conversions', 'totalRevenue'];
                    $orderBy = 'screenPageViews';
                    break;
                case 'geo':
                    $dimensions = ['country', 'city'];
                    $metrics = ['activeUsers', 'newUsers', 'sessions', 'engagementRate', 'conversions', 'totalRevenue'];
                    $orderBy = 'activeUsers';
                    break;
                default:
                     return response()->json(['error' => "Tipe laporan '{$reportType}' tidak valid. Gunakan 'pages' atau 'geo'."], 400);
            }
            
            $reportData = $this->runAdvancedHistoricalReport($analyticsData, $propertyId, $dateRangeConfig, $dimensions, $metrics, $orderBy, $filters);

            $rawTotals = $reportData['totals'] ?? [];
            $formattedTotals = [];
            if ($reportType === 'pages') {
                $totalActiveUsers = (float)($rawTotals['activeUsers'] ?? 0);
                $totalScreenPageViews = (float)($rawTotals['screenPageViews'] ?? 0);
                $formattedTotals = [
                    'activeUsers' => (int)$totalActiveUsers,
                    'viewsPerUser' => $totalActiveUsers > 0 ? round($totalScreenPageViews / $totalActiveUsers, 2) : 0,
                    'averageSessionDurationFormatted' => $this->formatDuration((float)($rawTotals['averageSessionDuration'] ?? 0)),
                    'eventCount' => (int)($rawTotals['eventCount'] ?? 0),
                    'conversions' => (int)($rawTotals['conversions'] ?? 0),
                    'totalRevenue' => round((float)($rawTotals['totalRevenue'] ?? 0), 2),
                ];
            } else { 
                $formattedTotals = $this->formatTotals_new($rawTotals);
            }

            return response()->json([
                'metadata' => [
                    'application' => $appConfig['name'],
                    'reportType' => $reportType,
                    'dateRange' => $dateRangeConfig,
                    'appliedFilters' => $this->getAppliedFiltersForMetadata($request),
                ],
                'totals' => $formattedTotals,
                'rows' => $reportData['rows'] ?? [],
            ]);
        } catch (Exception $e) {
            return $this->handleApiException("Laporan Fleksibel '{$appKey}'", $e);
        }
    }
    
    // ===================================================================
    // FUNGSI-FUNGSI LAMA (DIPERTAHANKAN UNTUK KOMPATIBILITAS)
    // ===================================================================
    
    public function fetchGeographyReport(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');

            $period = $request->query('period', 'last_7_days');
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            $dateRangeConfig = $this->getDateRangeFromPeriod($period, $startDate, $endDate);

            $dimensions = ['country', 'city'];
            $metrics = [
                'activeUsers',
                'newUsers',
                'sessions',
                'engagementRate',
                'conversions',
                'totalRevenue'
            ];
            
            $reportData = $this->runHistoricalReport(
                $analyticsData, 
                $propertyId, 
                $dateRangeConfig, 
                $dimensions, 
                $metrics, 
                'activeUsers',
                250
            );

            $formattedRows = [];
            if (!empty($reportData['rows'])) {
                foreach ($reportData['rows'] as $row) {
                    $formattedRows[] = [
                        'country' => $row['country'],
                        'city' => $row['city'],
                        'activeUsers' => (int)($row['activeUsers'] ?? 0),
                        'newUsers' => (int)($row['newUsers'] ?? 0),
                        'sessions' => (int)($row['sessions'] ?? 0),
                        'engagementRate' => round((float)($row['engagementRate'] ?? 0) * 100, 2) . '%',
                        'conversions' => (int)($row['conversions'] ?? 0),
                        'totalRevenue' => round((float)($row['totalRevenue'] ?? 0), 2),
                    ];
                }
            }
            
            $totals = $reportData['totals'] ?? [];
            $formattedTotals = [
                'activeUsers' => (int)($totals['activeUsers'] ?? 0),
                'newUsers' => (int)($totals['newUsers'] ?? 0),
                'sessions' => (int)($totals['sessions'] ?? 0),
                'engagementRate' => round((float)($totals['engagementRate'] ?? 0) * 100, 2) . '%',
                'conversions' => (int)($totals['conversions'] ?? 0),
                'totalRevenue' => round((float)($totals['totalRevenue'] ?? 0), 2),
            ];

            return response()->json([
                'metadata' => [
                    'period' => $period,
                    'dateRange' => $dateRangeConfig,
                ],
                'totals' => $formattedTotals,
                'rows' => $formattedRows
            ]);

        } catch (Exception $e) {
            return $this->handleApiException('Laporan Geografi', $e);
        }
    }

    public function fetchPagesReport(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');
            $period = $request->query('period', 'last_7_days');
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');
            $dateRangeConfig = $this->getDateRangeFromPeriod($period, $startDate, $endDate);
            $dimensions = ['pageTitle'];
            $metrics = ['activeUsers','screenPageViews','averageSessionDuration','eventCount','conversions','totalRevenue'];
            
            $reportData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRangeConfig, $dimensions, $metrics, 'screenPageViews', 100);

            $formattedRows = [];
            if (!empty($reportData['rows'])) {
                foreach ($reportData['rows'] as $row) {
                    $activeUsers = (float)($row['activeUsers'] ?? 0);
                    $screenPageViews = (float)($row['screenPageViews'] ?? 0);
                    $formattedRows[] = [
                        'pageTitle' => $row['pageTitle'],
                        'activeUsers' => (int)$activeUsers,
                        'viewsPerUser' => $activeUsers > 0 ? round($screenPageViews / $activeUsers, 2) : 0,
                        'averageSessionDurationFormatted' => $this->formatDuration((float)($row['averageSessionDuration'] ?? 0)),
                        'eventCount' => (int)($row['eventCount'] ?? 0),
                        'conversions' => (int)($row['conversions'] ?? 0),
                        'totalRevenue' => round((float)($row['totalRevenue'] ?? 0), 2),
                    ];
                }
            }
            
            $totals = $reportData['totals'] ?? [];
            $totalActiveUsers = (float)($totals['activeUsers'] ?? 0);
            $totalScreenPageViews = (float)($totals['screenPageViews'] ?? 0);
            $formattedTotals = [
                'activeUsers' => (int)$totalActiveUsers,
                'viewsPerUser' => $totalActiveUsers > 0 ? round($totalScreenPageViews / $totalActiveUsers, 2) : 0,
                'averageSessionDurationFormatted' => $this->formatDuration((float)($totals['averageSessionDuration'] ?? 0)),
                'eventCount' => (int)($totals['eventCount'] ?? 0),
                'conversions' => (int)($totals['conversions'] ?? 0),
                'totalRevenue' => round((float)($totals['totalRevenue'] ?? 0), 2),
            ];

            return response()->json([
                'metadata' => ['period' => $period,'dateRange' => $dateRangeConfig],
                'totals' => $formattedTotals,
                'rows' => $formattedRows
            ]);

        } catch (Exception $e) {
            return $this->handleApiException('Laporan Halaman & Layar', $e);
        }
    }

    public function fetchRealtimeData()
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');

            $allAppsRealtimeData = [];

            foreach ($this->applications as $appKey => $appConfig) {
                
                $realtimeFilter = new FilterExpression([
                    'filter' => new Filter([
                        'field_name' => 'unifiedScreenName',
                        'string_filter' => new StringFilter(['value' => $appConfig['page_path_filter'], 'match_type' => 'CONTAINS'])
                    ])
                ]);

                $usersByPage = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['unifiedScreenName', 'deviceCategory'], ['activeUsers'], $realtimeFilter);
                $activityFeed = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['minutesAgo', 'unifiedScreenName', 'city'], ['activeUsers'], $realtimeFilter);
                
                $usersByLocation = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['city'], ['activeUsers'], $realtimeFilter);
                $totalActiveUsersForApp = collect($usersByLocation['rows'] ?? [])->sum('activeUsers');
                
                $appData = [
                    'app_key' => $appKey,
                    'name' => $appConfig['name'],
                    'totalActiveUsers' => (int) $totalActiveUsersForApp,
                    'reports' => [
                        'byPage' => $usersByPage['rows'] ?? [],
                        'activityFeed' => $activityFeed['rows'] ?? [],
                        'byLocation' => [], 
                        'byPlatform' => [],
                        'byAudience' => [],
                    ]
                ];
                
                $allAppsRealtimeData[] = $appData;
            }

            return response()->json(['data' => $allAppsRealtimeData]);

        } catch (Exception $e) {
            return $this->handleApiException('Realtime Details', $e);
        }
    }

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
            $pageData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['pageTitle', 'pagePath'], ['screenPageViews', 'sessions', 'engagementRate', 'conversions'], 'screenPageViews');
            $geoData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['country', 'city'], ['activeUsers', 'newUsers', 'sessions', 'engagementRate', 'conversions'], 'activeUsers');
            $trafficSourceData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['sessionSourceMedium'], ['sessions', 'activeUsers', 'newUsers', 'engagementRate', 'conversions'], 'sessions');
            $techData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['deviceCategory', 'browser', 'operatingSystem'], ['sessions', 'activeUsers'], 'sessions');
            $dailyTrendData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['date'], ['activeUsers', 'sessions'], null, 100);
            $conversionEventData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['eventName'], ['conversions'], 'conversions');
            $landingPageData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['landingPage'], ['sessions', 'newUsers', 'engagementRate'], 'sessions');
            $retentionData = $this->runCohortReport($analyticsData, $propertyId);
            $summaryTotals = $geoData['totals'];
            $summary = ['activeUsers' => (int) ($summaryTotals['activeUsers'] ?? 0), 'newUsers' => (int) ($summaryTotals['newUsers'] ?? 0), 'sessions' => (int) ($summaryTotals['sessions'] ?? 0), 'conversions' => (int) ($summaryTotals['conversions'] ?? 0), 'screenPageViews' => (int) ($pageData['totals']['screenPageViews'] ?? 0), 'engagementRate' => round((float)($summaryTotals['engagementRate'] ?? 0) * 100, 2) . '%', 'averageSessionDuration' => gmdate("i:s", (int)($pageData['totals']['averageSessionDuration'] ?? 0)),];
            return response()->json(['summary' => $summary, 'reports' => ['dailyTrends' => $dailyTrendData['rows'] ?? [], 'pages' => $pageData['rows'] ?? [], 'landingPages' => $landingPageData['rows'] ?? [], 'geography' => $geoData['rows'] ?? [], 'trafficSources' => $trafficSourceData['rows'] ?? [], 'conversionEvents' => $conversionEventData['rows'] ?? [], 'technology' => $techData['rows'] ?? [], 'userRetention' => $retentionData ?? []]]);
        } catch (Exception $e) { return $this->handleApiException('Historis', $e); }
    }
    
    // ===================================================================
    // FUNGSI-FUNGSI HELPER
    // ===================================================================

    private function getDateRangeFromPeriod(string $period, ?string $customStart, ?string $customEnd): array
    {
        $today = Carbon::today();
        switch ($period) {
            case 'today': return ['start_date' => $today->format('Y-m-d'), 'end_date' => $today->format('Y-m-d')];
            case 'yesterday': $yesterday = Carbon::yesterday(); return ['start_date' => $yesterday->format('Y-m-d'), 'end_date' => $yesterday->format('Y-m-d')];
            case 'this_week': return ['start_date' => $today->copy()->startOfWeek(Carbon::SUNDAY)->format('Y-m-d'), 'end_date' => $today->format('Y-m-d')];
            case 'last_week': $startOfLastWeek = $today->copy()->subWeek()->startOfWeek(Carbon::SUNDAY); $endOfLastWeek = $today->copy()->subWeek()->endOfWeek(Carbon::SATURDAY); return ['start_date' => $startOfLastWeek->format('Y-m-d'), 'end_date' => $endOfLastWeek->format('Y-m-d')];
            case 'last_7_days': return ['start_date' => '7daysAgo', 'end_date' => 'today'];
            case 'last_14_days': return ['start_date' => '14daysAgo', 'end_date' => 'today'];
            case 'last_28_days': return ['start_date' => '28daysAgo', 'end_date' => 'today'];
            case 'last_30_days': return ['start_date' => '30daysAgo', 'end_date' => 'today'];
            case 'last_60_days': return ['start_date' => '60daysAgo', 'end_date' => 'today'];
            case 'custom': if ($customStart && $customEnd) { return ['start_date' => Carbon::parse($customStart)->format('Y-m-d'), 'end_date' => Carbon::parse($customEnd)->format('Y-m-d')]; } return ['start_date' => '7daysAgo', 'end_date' => 'today'];
            default: return ['start_date' => '7daysAgo', 'end_date' => 'today'];
        }
    }
    
    private function formatDuration(float $seconds): string
    {
        if ($seconds < 1) { return '0s'; }
        $seconds = (int)$seconds; $m = floor($seconds / 60); $s = $seconds % 60; $h = floor($m / 60); $m = $m % 60;
        $parts = [];
        if ($h > 0) { $parts[] = $h . 'h'; }
        if ($m > 0) { $parts[] = $m . 'm'; }
        if ($s > 0 || empty($parts)) { $parts[] = $s . 's'; }
        return implode(' ', $parts);
    }
    
    private function runRealtimeReportHelper($analyticsData, $propertyId, array $dimensions, array $metrics, ?FilterExpression $filterExpression = null): array
    {
        $dimensionObjects = array_map(fn($name) => new Dimension(['name' => $name]), $dimensions);
        $metricObjects = array_map(fn($name) => new Metric(['name' => $name]), $metrics);
        $request = new RunRealtimeReportRequest(['dimensions' => $dimensionObjects, 'metrics' => $metricObjects, 'limit' => 250]);
        if ($filterExpression) {
            $request->setDimensionFilter($filterExpression);
        }
        $response = $analyticsData->properties->runRealtimeReport("properties/{$propertyId}", $request);
        $result = ['rows' => []];
        foreach ($response->getRows() as $row) {
            $rowData = [];
            foreach ($row->getDimensionValues() as $i => $dimValue) { $rowData[$dimensions[$i]] = $dimValue->getValue(); }
            foreach ($row->getMetricValues() as $i => $metricValue) { $rowData[$metrics[$i]] = (int) $metricValue->getValue(); }
            $result['rows'][] = $rowData;
        }
        return $result;
    }
    
    private function runHistoricalReport($analyticsData, $propertyId, array $dateRangeConfig, array $dimensions, array $metrics, ?string $orderByMetric = null, int $limit = 25): array
    {
        $dimensionObjects = array_map(fn($name) => new Dimension(['name' => $name]), $dimensions);
        $metricObjects = array_map(fn($name) => new Metric(['name' => $name]), $metrics);
        $dateRange = new GoogleDateRange(['start_date' => $dateRangeConfig['start_date'], 'end_date' => $dateRangeConfig['end_date']]);
        $requestConfig = ['dateRanges' => [$dateRange], 'dimensions' => $dimensionObjects, 'metrics' => $metricObjects, 'limit' => $limit, 'metricAggregations' => ['TOTAL']];
        if ($orderByMetric) { $requestConfig['orderBys'] = [new OrderBy(['metric' => new MetricOrderBy(['metric_name' => $orderByMetric]), 'desc' => true])]; }
        $request = new RunReportRequest($requestConfig);
        $response = $analyticsData->properties->runReport('properties/' . $propertyId, $request);
        $result = ['rows' => [], 'totals' => []];
        foreach ($response->getRows() as $row) {
            $rowData = [];
            foreach ($row->getDimensionValues() as $i => $dimValue) { if ($dimensions[$i] === 'date') { $rowData[$dimensions[$i]] = Carbon::createFromFormat('Ymd', $dimValue->getValue())->format('Y-m-d'); } else { $rowData[$dimensions[$i]] = $dimValue->getValue(); } }
            foreach ($row->getMetricValues() as $i => $metricValue) { $rowData[$metrics[$i]] = $metricValue->getValue(); }
            $result['rows'][] = $rowData;
        }
        if ($response->getTotals() && count($response->getTotals()) > 0) { $totalsRow = $response->getTotals()[0]; foreach ($totalsRow->getMetricValues() as $i => $metricValue) { $result['totals'][$metrics[$i]] = $metricValue->getValue(); } }
        if ($orderByMetric === null && !empty($result['rows']) && isset($result['rows'][0]['date'])) { usort($result['rows'], fn($a, $b) => $a['date'] <=> $b['date']); }
        return $result;
    }
    
    private function runCohortReport($analyticsData, $propertyId): array
    {
        $today = Carbon::today();
        $cohortSpec = new CohortSpec(['cohorts' => [new \Google\Service\AnalyticsData\Cohort(['name' => 'week0', 'dimension' => 'firstTouchDate','dateRange' => new GoogleDateRange(['start_date' => $today->copy()->subDays(7)->format('Y-m-d'),'end_date' => $today->copy()->format('Y-m-d')])]),new \Google\Service\AnalyticsData\Cohort(['name' => 'week1', 'dimension' => 'firstTouchDate','dateRange' => new GoogleDateRange(['start_date' => $today->copy()->subDays(14)->format('Y-m-d'),'end_date' => $today->copy()->subDays(8)->format('Y-m-d')])]),new \Google\Service\AnalyticsData\Cohort(['name' => 'week2', 'dimension' => 'firstTouchDate','dateRange' => new GoogleDateRange(['start_date' => $today->copy()->subDays(21)->format('Y-m-d'),'end_date' => $today->copy()->subDays(15)->format('Y-m-d')])]),new \Google\Service\AnalyticsData\Cohort(['name' => 'week3', 'dimension' => 'firstTouchDate','dateRange' => new GoogleDateRange(['start_date' => $today->copy()->subDays(28)->format('Y-m-d'),'end_date' => $today->copy()->subDays(22)->format('Y-m-d')])]),],'cohortsRange' => new \Google\Service\AnalyticsData\CohortsRange(['granularity' => 'WEEKLY', 'start_offset' => 0, 'end_offset' => 4]),'cohortReportSettings' => new \Google\Service\AnalyticsData\CohortReportSettings(['accumulate' => false]),]);
        $request = new RunReportRequest(['cohortSpec' => $cohortSpec, 'dimensions' => [new Dimension(['name' => 'cohort']), new Dimension(['name' => 'cohortNthWeek'])], 'metrics' => [new Metric(['name' => 'cohortActiveUsers'])], ]);
        $response = $analyticsData->properties->runReport('properties/' . $propertyId, $request);
        $retentionTable = [];
        foreach ($response->getRows() as $row) { $cohortName = $row->getDimensionValues()[0]->getValue(); $weekNumber = (int) str_replace('cohortNthWeek.value.', '', $row->getDimensionValues()[1]->getValue()); $activeUsers = (int) $row->getMetricValues()[0]->getValue(); if (!isset($retentionTable[$cohortName])) { $retentionTable[$cohortName] = ['cohort_date_range' => '', 'users_per_week' => [], 'total_users' => 0]; } if ($weekNumber === 0) { $retentionTable[$cohortName]['total_users'] = $activeUsers; } $retentionTable[$cohortName]['users_per_week'][$weekNumber] = $activeUsers; }
        $formattedOutput = [];
        foreach($retentionTable as $name => $data) { $totalUsers = $data['total_users']; if ($totalUsers === 0) continue; $weeklyRetention = []; foreach($data['users_per_week'] as $week => $users) { $weeklyRetention["Week " . $week] = [ 'users' => $users, 'percentage' => round(($users / $totalUsers) * 100, 2) ]; } $dateRange = collect($cohortSpec->getCohorts())->firstWhere('name', $name)->getDateRange(); $startDate = Carbon::parse($dateRange->getStartDate())->format('d M'); $endDate = Carbon::parse($dateRange->getEndDate())->format('d M'); $formattedOutput[] = [ 'cohort' => "Users from: {$startDate} - {$endDate}", 'total_initial_users' => $totalUsers, 'retention' => $weeklyRetention, ]; }
        return $formattedOutput;
    }
    
    private function handleApiException(string $context, Exception $e): \Illuminate\Http\JsonResponse
    {
        $message = $e->getMessage(); $decoded = json_decode($message, true); if (json_last_error() === JSON_ERROR_NONE && isset($decoded['error']['message'])) { $message = $decoded['error']['message']; }
        Log::error("Google Analytics API Error ({$context}): " . $message, ['trace' => $e->getTraceAsString()]);
        return response()->json(['error' => "Gagal mengambil data {$context}: " . $message], 500);
    }
    
    private function runAdvancedHistoricalReport($analyticsData, $propertyId, array $dateRangeConfig, array $dimensions, array $metrics, ?string $orderByMetric, ?array $filters, int $limit = 250): array
    {
        $request = new RunReportRequest(['dateRanges' => [new GoogleDateRange($dateRangeConfig)],'dimensions' => array_map(fn($name) => new Dimension(['name' => $name]), $dimensions),'metrics' => array_map(fn($name) => new Metric(['name' => $name]), $metrics),'limit' => $limit,'metricAggregations' => ['TOTAL'],]);
        if ($filters) { $request->setDimensionFilter(new FilterExpression(['and_group' => new \Google\Service\AnalyticsData\FilterExpressionList(['expressions' => $filters])])); }
        if ($orderByMetric) { $request->setOrderBys([new OrderBy(['metric' => new MetricOrderBy(['metric_name' => $orderByMetric]), 'desc' => true])]); }
        $response = $analyticsData->properties->runReport('properties/' . $propertyId, $request);
        $result = ['rows' => [], 'totals' => []];
        foreach($response->getRows()as $r){$rd=[];foreach($r->getDimensionValues()as $i=>$d){$rd[$dimensions[$i]]=$d->getValue();}foreach($r->getMetricValues()as $i=>$m){$rd[$metrics[$i]]=$m->getValue();}$result['rows'][]=$rd;}if($response->getTotals()&&count($response->getTotals())>0){$t=$response->getTotals()[0];foreach($t->getMetricValues()as $i=>$m){$result['totals'][$metrics[$i]]=$m->getValue();}}return $result;
    }
    
    private function buildAdvancedFilters(Request $request, array $appConfig): array
    {
        $filters = [];
        $filters[] = new FilterExpression(['filter' => new Filter(['field_name' => 'pagePath', 'string_filter' => new StringFilter(['value' => $appConfig['page_path_filter'], 'match_type' => 'CONTAINS'])])]);
        if ($request->filled('pageTitle')) { $filters[] = new FilterExpression(['filter' => new Filter(['field_name' => 'pageTitle', 'string_filter' => new StringFilter(['value' => $request->query('pageTitle'), 'match_type' => 'CONTAINS'])])]); }
        if ($request->filled('country')) { $filters[] = new FilterExpression(['filter' => new Filter(['field_name' => 'country', 'string_filter' => new StringFilter(['value' => $request->query('country'), 'match_type' => 'CONTAINS'])])]); }
        return $filters;
    }
    
    private function getDateRangeFromPeriod_new(Request $request): array
    {
        $period = $request->query('period', 'last_7_days');
        $customStart = $request->query('start_date');
        $customEnd = $request->query('end_date');
        if (preg_match('/^last_(\d+)_days$/', $period, $matches)) {
            $days = $matches[1];
            if ($days > 0) { return ['start_date' => "{$days}daysAgo", 'end_date' => 'today']; }
        }
        switch ($period) {
            case 'today': return ['start_date' => 'today', 'end_date' => 'today'];
            case 'yesterday': return ['start_date' => 'yesterday', 'end_date' => 'yesterday'];
            case 'custom':
                if ($customStart && $customEnd) { return ['start_date' => Carbon::parse($customStart)->format('Y-m-d'), 'end_date' => Carbon::parse($customEnd)->format('Y-m-d')]; }
                return ['start_date' => '7daysAgo', 'end_date' => 'today'];
            default: return ['start_date' => '7daysAgo', 'end_date' => 'today'];
        }
    }
    
    private function getAppliedFiltersForMetadata(Request $request): array
    {
        $metadata = [];
        if ($request->filled('pageTitle')) { $metadata['pageTitle_contains'] = $request->query('pageTitle'); }
        if ($request->filled('country')) { $metadata['country_is'] = $request->query('country'); }
        return $metadata;
    }
    
    private function formatTotals_new(array $totals): array {
        $f=[];
        foreach($totals as $k=>$v){
            if($k === 'engagementRate'){ $f[$k]=round((float)$v*100,2).'%'; }
            elseif($k === 'averageSessionDuration'){ $f[$k]=$this->formatDuration((float)$v); }
            elseif($k === 'totalRevenue'){ $f[$k]=round((float)$v,2); }
            else { $f[$k]=(int)$v; }
        }
        return $f;
    }
}