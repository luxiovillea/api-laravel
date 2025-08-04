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
use App\Models\Aplikasi; // Import model Aplikasi

/**
 * @OA\Info(
 *     title="Google Analytics Data API Wrapper - Dynamic Applications",
 *     version="2.0.0",
 *     description="API untuk mengambil dan menyajikan data dari Google Analytics 4 (GA4) Data API. Mendukung multiple aplikasi yang dikonfigurasi secara dinamis melalui database.",
 *     @OA\Contact(
 *         email="support@example.com",
 *         name="Support Team"
 *     )
 * )
 * @OA\Server(
 *     url="https://api-laravel-production-39d6.up.railway.app/api",
 *     description="Production API Server"
 * )
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Local Development Server"
 * )
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     @OA\Property(property="error", type="string", example="Gagal mengambil data: Pesan error dari API.")
 * )
 */
class AnalyticsController extends Controller
{
    /**
     * Mendapatkan konfigurasi aplikasi dari database
     */
    private function getApplicationsFromDatabase(): array
    {
        $applications = [];
        $aplikasiList = Aplikasi::active()->get();
        
        foreach ($aplikasiList as $aplikasi) {
            $applications[$aplikasi->key_aplikasi] = [
                'id' => $aplikasi->id,
                'name' => $aplikasi->nama_aplikasi,
                'page_path_filter' => $aplikasi->page_path_filter,
                'property_id' => $aplikasi->property_id,
                'additional_config' => $aplikasi->konfigurasi_tambahan ?? [],
            ];
        }
        
        return $applications;
    }

    /**
     * Mendapatkan konfigurasi aplikasi spesifik berdasarkan key
     */
    private function getApplicationByKey(string $appKey): ?array
    {
        $aplikasi = Aplikasi::where('key_aplikasi', $appKey)->active()->first();
        
        if (!$aplikasi) {
            return null;
        }
        
        return [
            'id' => $aplikasi->id,
            'name' => $aplikasi->nama_aplikasi,
            'page_path_filter' => $aplikasi->page_path_filter,
            'property_id' => $aplikasi->property_id,
            'additional_config' => $aplikasi->konfigurasi_tambahan ?? [],
        ];

        
    }

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

    /**
     * @OA\Get(
     *     path="/analytics/dashboard-summary",
     *     summary="Ringkasan Dashboard Utama - Dynamic Applications",
     *     description="Menghasilkan ringkasan data komprehensif untuk semua aplikasi yang terdaftar di database dalam satu periode waktu.",
     *     operationId="getDashboardSummary",
     *     tags={"Dashboard"},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Periode waktu untuk laporan. Contoh: 'last_7_days', 'last_30_days', 'custom'.",
     *         required=false,
     *         @OA\Schema(type="string", default="last_7_days")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Tanggal mulai (Y-m-d) jika periode 'custom'.",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="Tanggal akhir (Y-m-d) jika periode 'custom'.",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sukses mendapatkan data ringkasan.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                      property="applications",
     *                      type="array",
     *                      @OA\Items(
     *                          type="object",
     *                          @OA\Property(property="id", type="integer", example=1),
     *                          @OA\Property(property="app_key", type="string", example="lapakami"),
     *                          @OA\Property(property="db_id", type="integer", example=1),
     *                          @OA\Property(property="name", type="string", example="Aplikasi Lapakami"),
     *                          @OA\Property(property="property_id", type="string", example="123456789"),
     *                          @OA\Property(property="key_metrics", type="object",
     *                              @OA\Property(property="total_visitor", type="integer", example=1500),
     *                              @OA\Property(property="active_user", type="integer", example=1200),
     *                              @OA\Property(property="new_user", type="integer", example=300),
     *                              @OA\Property(property="page_views", type="integer", example=5000)
     *                          ),
     *                          @OA\Property(property="engagement", type="object",
     *                              @OA\Property(property="engagement_rate", type="string", example="65.50%"),
     *                              @OA\Property(property="average_session_duration", type="string", example="2m 30s")
     *                          ),
     *                          @OA\Property(property="top_sources", type="object",
     *                              @OA\Property(property="geography", type="object",
     *                                  @OA\Property(property="city", type="string", example="Jakarta"),
     *                                  @OA\Property(property="country", type="string", example="Indonesia")
     *                              ),
     *                              @OA\Property(property="traffic_channel", type="string", example="google / organic")
     *                          ),
     *                          @OA\Property(property="business", type="object",
     *                              @OA\Property(property="conversions", type="integer", example=50)
     *                          ),
     *                          @OA\Property(property="technology_overview", type="array", @OA\Items(
     *                              type="object",
     *                              @OA\Property(property="deviceCategory", type="string", example="desktop"),
     *                              @OA\Property(property="browser", type="string", example="Chrome"),
     *                              @OA\Property(property="operatingSystem", type="string", example="Windows"),
     *                              @OA\Property(property="sessions", type="integer", example=1000),
     *                              @OA\Property(property="activeUsers", type="integer", example=800)
     *                          ))
     *                      )
     *                 ),
     *                 @OA\Property(property="meta", type="object",
     *                      @OA\Property(property="total", type="integer", example=3),
     *                      @OA\Property(property="page", type="integer", example=1),
     *                      @OA\Property(property="limit", type="integer", example=3)
     *                 )
     *             ),
     *             @OA\Property(
     *                  property="metadata",
     *                  type="object",
     *                  @OA\Property(property="period", type="string", example="last_7_days"),
     *                  @OA\Property(property="dateRange", type="object",
     *                      @OA\Property(property="start_date", type="string", example="7daysAgo"),
     *                      @OA\Property(property="end_date", type="string", example="today")
     *                  )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Terjadi kesalahan pada server atau API Google.",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function getDashboardSummary(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $dateRange = $this->getDateRangeFromPeriod_new($request);
            $applications = $this->getApplicationsFromDatabase();
            
            if (empty($applications)) {
                return response()->json([
                    'data' => [
                        'applications' => [],
                        'meta' => ['total' => 0, 'page' => 1, 'limit' => 0],
                    ],
                    'metadata' => [
                        'period' => $request->query('period', 'last_7_days'),
                        'dateRange' => $dateRange,
                        'message' => 'Tidak ada aplikasi aktif yang ditemukan'
                    ]
                ]);
            }

            $summaryData = [];
            $appIdCounter = 1;

            foreach ($applications as $appKey => $appConfig) {
                $propertyId = $appConfig['property_id']; // Gunakan property_id dari database
                
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
                    'db_id' => $appConfig['id'], // ID dari database
                    'name' => $appConfig['name'],
                    'property_id' => $appConfig['property_id'], // Tampilkan property_id
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
                'metadata' => [ 
                    'period' => $request->query('period', 'last_7_days'), 
                    'dateRange' => $dateRange,
                    'applications_loaded_from_database' => count($applications)
                ]
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
     * @OA\Get(
     *     path="/analytics/realtime-summary",
     *     summary="Ringkasan Realtime per Aplikasi - Dynamic",
     *     description="Mengambil data jumlah pengguna aktif saat ini (realtime) untuk setiap aplikasi yang terdaftar di database.",
     *     operationId="getRealtimeSummary",
     *     tags={"Dashboard", "Realtime"},
     *     @OA\Response(
     *         response=200,
     *         description="Sukses mendapatkan data realtime.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="app_key", type="string", example="lapakami"),
     *                      @OA\Property(property="db_id", type="integer", example=1),
     *                      @OA\Property(property="name", type="string", example="Aplikasi Lapakami"),
     *                      @OA\Property(property="property_id", type="string", example="123456789"),
     *                      @OA\Property(property="active_users_now", type="integer", example=15)
     *                  )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Terjadi kesalahan pada server atau API Google.",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function getRealtimeSummary()
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $applications = $this->getApplicationsFromDatabase();
            $realtimeData = [];

            foreach ($applications as $appKey => $appConfig) {
                $propertyId = $appConfig['property_id']; //Gunakan property_id dari database
                
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
                    'db_id' => $appConfig['id'],
                    'name' => $appConfig['name'],
                    'property_id' => $appConfig['property_id'],
                    'active_users_now' => (int)$totalActiveUsers,
                ];
            }

            return response()->json(['data' => $realtimeData]);

        } catch (Exception $e) {
            return $this->handleApiException("Ringkasan Realtime", $e);
        }
    }

    // LAPORAN DETAIL
    
    /**
     * @OA\Get(
     *     path="/analytics/{appKey}/report",
     *     summary="Laporan Detail per Aplikasi - Dynamic",
     *     description="Menghasilkan laporan detail (seperti halaman populer atau geografi) untuk aplikasi tertentu dengan filter opsional. Data aplikasi diambil dari database.",
     *     operationId="generateReport",
     *     tags={"Detail Reports"},
     *     @OA\Parameter(
     *         name="appKey",
     *         in="path",
     *         description="Kunci unik aplikasi yang terdaftar di database (contoh: 'lapakami').",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Jenis laporan yang diminta.",
     *         required=true,
     *         @OA\Schema(type="string", enum={"pages", "geo"})
     *     ),
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Periode waktu untuk laporan.",
     *         required=false,
     *         @OA\Schema(type="string", default="last_7_days")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Tanggal mulai (Y-m-d) jika periode 'custom'.",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *      @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="Tanggal akhir (Y-m-d) jika periode 'custom'.",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *      @OA\Parameter(
     *         name="pageTitle",
     *         in="query",
     *         description="Filter berdasarkan judul halaman (contains).",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *      @OA\Parameter(
     *         name="country",
     *         in="query",
     *         description="Filter berdasarkan nama negara (contains).",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Laporan berhasil dibuat.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="metadata", type="object"),
     *             @OA\Property(property="totals", type="object", description="Total metrik untuk laporan."),
     *             @OA\Property(property="rows", type="array", @OA\Items(type="object"), description="Baris data laporan.")
     *         )
     *     ),
     *      @OA\Response(
     *         response=400,
     *         description="Bad Request, contoh: tipe laporan tidak valid.",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *      @OA\Response(
     *         response=404,
     *         description="Not Found, contoh: appKey tidak ditemukan.",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Terjadi kesalahan pada server atau API Google.",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function generateReport(Request $request, string $appKey)
    {
        try {
            $appConfig = $this->getApplicationByKey($appKey);
            
            if (!$appConfig) {
                return response()->json(['error' => "Aplikasi '{$appKey}' tidak ditemukan atau tidak aktif."], 404);
            }

            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = $appConfig['property_id']; // Gunakan property_id dari database
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
                    'app_key' => $appKey,
                    'db_id' => $appConfig['id'],
                    'property_id' => $appConfig['property_id'],
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

    /**
     * @OA\Get(
     *     path="/analytics-data",
     *     summary="Data Realtime Detail - Legacy dengan Support Dynamic Apps",
     *     description="Menyediakan data realtime yang terperinci, sekarang mendukung multiple property ID dari database.",
     *     operationId="fetchRealtimeData",
     *     tags={"Legacy Reports", "Realtime"},
     *     @OA\Parameter(
     *         name="app_key",
     *         in="query",
     *         description="Key aplikasi spesifik, jika tidak diisi akan menggunakan aplikasi pertama yang aktif",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sukses mendapatkan data realtime detail.",
     *         @OA\JsonContent(type="object",
     *              @OA\Property(property="totalActiveUsers", type="integer"),
     *              @OA\Property(property="metadata", type="object",
     *                  @OA\Property(property="app_key", type="string"),
     *                  @OA\Property(property="app_name", type="string"),
     *                  @OA\Property(property="property_id", type="string")
     *              ),
     *              @OA\Property(property="reports", type="object",
     *                  @OA\Property(property="byPage", type="array", @OA\Items(type="object")),
     *                  @OA\Property(property="byLocation", type="array", @OA\Items(type="object")),
     *                  @OA\Property(property="byPlatform", type="array", @OA\Items(type="object")),
     *                  @OA\Property(property="byAudience", type="array", @OA\Items(type="object")),
     *                  @OA\Property(property="activityFeed", type="array", @OA\Items(type="object")),
     *              )
     *         )
     *     ),
     *     @OA\Response(response=500, description="Error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function fetchRealtimeData(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            
            // Ambil aplikasi berdasarkan app_key atau gunakan yang pertama jika tidak ada
            $appKey = $request->query('app_key');
            if ($appKey) {
                $appConfig = $this->getApplicationByKey($appKey);
                if (!$appConfig) {
                    return response()->json(['error' => "Aplikasi '{$appKey}' tidak ditemukan atau tidak aktif."], 404);
                }
            } else {
                $applications = $this->getApplicationsFromDatabase();
                if (empty($applications)) {
                    return response()->json(['error' => "Tidak ada aplikasi aktif yang ditemukan."], 404);
                }
                $appKey = array_key_first($applications);
                $appConfig = $applications[$appKey];
            }
            
            $propertyId = $appConfig['property_id'];

            $usersByPage = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['unifiedScreenName', 'deviceCategory'], ['activeUsers']);
            $usersByLocation = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['country', 'city'], ['activeUsers']);
            $usersByPlatform = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['platform'], ['activeUsers']);
            $usersByAudience = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['audienceName'], ['activeUsers']);
            $activityFeed = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['minutesAgo', 'unifiedScreenName', 'city'], ['activeUsers']);
            
            $totalActiveUsers = collect($usersByLocation['rows'] ?? [])->sum('activeUsers');
            
            return response()->json([
                'totalActiveUsers' => (int) $totalActiveUsers,
                'metadata' => [
                    'app_key' => $appKey,
                    'app_name' => $appConfig['name'],
                    'property_id' => $appConfig['property_id'],
                    'db_id' => $appConfig['id']
                ],
                'reports' => [
                    'byPage' => $usersByPage['rows'] ?? [],
                    'byLocation' => $usersByLocation['rows'] ?? [],
                    'byPlatform' => $usersByPlatform['rows'] ?? [],
                    'byAudience' => $usersByAudience['rows'] ?? [],
                    'activityFeed' => $activityFeed['rows'] ?? []
                ]
            ]);
        } catch (Exception $e) { 
            return $this->handleApiException('Realtime', $e); 
        }
    }

    // LEGACY FUNCTIONS - Tetap menggunakan env default untuk backward compatibility
    
    /**
     * @OA\Get(
     *     path="/analytics-historical",
     *     summary="Data Historis Komprehensif - Legacy",
     *     description="Menyediakan berbagai macam laporan historis dalam satu panggilan, menggunakan property ID default dari environment.",
     *     operationId="fetchHistoricalData",
     *     tags={"Legacy Reports"},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Periode waktu untuk laporan.",
     *         required=false,
     *         @OA\Schema(type="string", enum={"7days", "28days", "90days"}, default="28days")
     *     ),
     *      @OA\Parameter(
     *         name="app_key",
     *         in="query",
     *         description="Key aplikasi spesifik dari database, jika tidak diisi akan menggunakan property ID default dari env",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sukses mendapatkan data historis.",
     *         @OA\JsonContent(type="object",
     *              @OA\Property(property="summary", type="object"),
     *              @OA\Property(property="reports", type="object"),
     *              @OA\Property(property="metadata", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function fetchHistoricalData(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            
            // Support untuk app_key dinamis atau fallback ke env default
            $appKey = $request->query('app_key');
            $propertyId = env('GA_PROPERTY_ID'); // Default
            $appName = 'Default Application';
            
            if ($appKey) {
                $appConfig = $this->getApplicationByKey($appKey);
                if ($appConfig) {
                    $propertyId = $appConfig['property_id'];
                    $appName = $appConfig['name'];
                }
            }
            
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
            $summary = [
                'activeUsers' => (int) ($summaryTotals['activeUsers'] ?? 0), 
                'newUsers' => (int) ($summaryTotals['newUsers'] ?? 0), 
                'sessions' => (int) ($summaryTotals['sessions'] ?? 0), 
                'conversions' => (int) ($summaryTotals['conversions'] ?? 0), 
                'screenPageViews' => (int) ($pageData['totals']['screenPageViews'] ?? 0), 
                'engagementRate' => round((float)($summaryTotals['engagementRate'] ?? 0) * 100, 2) . '%', 
                'averageSessionDuration' => gmdate("i:s", (int)($pageData['totals']['averageSessionDuration'] ?? 0)),
            ];
            
            return response()->json([
                'summary' => $summary, 
                'reports' => [
                    'dailyTrends' => $dailyTrendData['rows'] ?? [], 
                    'pages' => $pageData['rows'] ?? [], 
                    'landingPages' => $landingPageData['rows'] ?? [], 
                    'geography' => $geoData['rows'] ?? [], 
                    'trafficSources' => $trafficSourceData['rows'] ?? [], 
                    'conversionEvents' => $conversionEventData['rows'] ?? [], 
                    'technology' => $techData['rows'] ?? [], 
                    'userRetention' => $retentionData ?? []
                ],
                'metadata' => [
                    'app_name' => $appName,
                    'app_key' => $appKey,
                    'property_id' => $propertyId,
                    'period' => $period,
                    'dateRange' => $dateRange
                ]
            ]);
        } catch (Exception $e) { 
            return $this->handleApiException('Historis', $e); 
        }
    }

    /**
     * @OA\Get(
     *     path="/analytics/geography-report",
     *     summary="Laporan Geografi - Legacy dengan Dynamic Support",
     *     description="Menghasilkan laporan demografi pengguna berdasarkan negara dan kota.",
     *     operationId="fetchGeographyReport",
     *     tags={"Legacy Reports"},
     *     @OA\Parameter(
     *         name="period", in="query", description="Periode waktu laporan.", required=false, @OA\Schema(type="string", default="last_7_days")
     *     ),
     *     @OA\Parameter(
     *         name="start_date", in="query", description="Tanggal mulai (Y-m-d) jika 'custom'.", required=false, @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date", in="query", description="Tanggal akhir (Y-m-d) jika 'custom'.", required=false, @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="app_key", in="query", description="Key aplikasi dari database", required=false, @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sukses mendapatkan laporan geografi.",
     *         @OA\JsonContent(type="object",
     *              @OA\Property(property="metadata", type="object"),
     *              @OA\Property(property="totals", type="object"),
     *              @OA\Property(property="rows", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=500, description="Error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function fetchGeographyReport(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            
            // Support untuk app_key dinamis atau fallback ke env default
            $appKey = $request->query('app_key');
            $propertyId = env('GA_PROPERTY_ID'); // Default
            $appName = 'Default Application';
            
            if ($appKey) {
                $appConfig = $this->getApplicationByKey($appKey);
                if ($appConfig) {
                    $propertyId = $appConfig['property_id'];
                    $appName = $appConfig['name'];
                }
            }

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
                    'app_key' => $appKey,
                    'app_name' => $appName,
                    'property_id' => $propertyId,
                ],
                'totals' => $formattedTotals,
                'rows' => $formattedRows
            ]);

        } catch (Exception $e) {
            return $this->handleApiException('Laporan Geografi', $e);
        }
    }

    /**
     * @OA\Get(
     *     path="/analytics/pages-report",
     *     summary="Laporan Halaman & Layar - Legacy dengan Dynamic Support",
     *     description="Menghasilkan laporan halaman yang paling banyak dilihat pengguna.",
     *     operationId="fetchPagesReport",
     *     tags={"Legacy Reports"},
     *     @OA\Parameter(
     *         name="period", in="query", description="Periode waktu laporan.", required=false, @OA\Schema(type="string", default="last_7_days")
     *     ),
     *     @OA\Parameter(
     *         name="start_date", in="query", description="Tanggal mulai (Y-m-d) jika 'custom'.", required=false, @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date", in="query", description="Tanggal akhir (Y-m-d) jika 'custom'.", required=false, @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="app_key", in="query", description="Key aplikasi dari database", required=false, @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sukses mendapatkan laporan halaman.",
     *         @OA\JsonContent(type="object",
     *              @OA\Property(property="metadata", type="object"),
     *              @OA\Property(property="totals", type="object"),
     *              @OA\Property(property="rows", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=500, description="Error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function fetchPagesReport(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            
            // Support untuk app_key dinamis atau fallback ke env default
            $appKey = $request->query('app_key');
            $propertyId = env('GA_PROPERTY_ID'); // Default
            $appName = 'Default Application';
            
            if ($appKey) {
                $appConfig = $this->getApplicationByKey($appKey);
                if ($appConfig) {
                    $propertyId = $appConfig['property_id'];
                    $appName = $appConfig['name'];
                }
            }
            
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
                'metadata' => [
                    'period' => $period,
                    'dateRange' => $dateRangeConfig,
                    'app_key' => $appKey,
                    'app_name' => $appName,
                    'property_id' => $propertyId,
                ],
                'totals' => $formattedTotals,
                'rows' => $formattedRows
            ]);

        } catch (Exception $e) {
            return $this->handleApiException('Laporan Halaman & Layar', $e);
        }
    }

    // ===================================================================
    // HELPER FUNCTIONS - Tetap sama seperti sebelumnya
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