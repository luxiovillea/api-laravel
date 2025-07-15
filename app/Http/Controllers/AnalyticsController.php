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
 * 
 * Versi ini menambahkan:
 * - Realtime: Laporan audiens & feed aktivitas.
 * - Historis: Laporan tren harian (untuk grafik), konversi per event, dan halaman landing.
 * - Fitur: Rentang waktu historis yang dinamis via query parameter.
 */
class AnalyticsController extends Controller
{
    /**
     * Menginisialisasi Google Client.
     */
    private function getGoogleClient(): Client
    {
        $serviceAccountPath = storage_path('app/service-account-credentials.json');
        if (!file_exists($serviceAccountPath)) {
            throw new Exception("File service-account-credentials.json tidak ditemukan di storage/app/.");
        }

        $client = new Client();
        $client->setAuthConfig($serviceAccountPath);
        $client->addScope('https://www.googleapis.com/auth/analytics.readonly');
        return $client;
    }

    /**
     * Mengambil data REALTIME dengan laporan tambahan.
     */
    public function fetchRealtimeData()
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');

            // Laporan standar
            $usersByPage = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['unifiedScreenName', 'deviceCategory'], ['activeUsers']);
            $usersByLocation = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['country', 'city'], ['activeUsers']);
            $usersByPlatform = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['platform'], ['activeUsers']);
            
            // BARU: Laporan Audiens Realtime
            $usersByAudience = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['audienceName'], ['activeUsers']);

            // BARU: Laporan "Activity Feed"
            $activityFeed = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['minutesAgo', 'unifiedScreenName', 'city'], ['activeUsers']);


            // Hitung total pengguna dari salah satu laporan untuk konsistensi
            $totalActiveUsers = collect($usersByLocation['rows'] ?? [])->sum('activeUsers');

            return response()->json([
                'totalActiveUsers' => (int) $totalActiveUsers,
                'reports' => [
                    'byPage'      => $usersByPage['rows'] ?? [],
                    'byLocation'  => $usersByLocation['rows'] ?? [],
                    'byPlatform'  => $usersByPlatform['rows'] ?? [],
                    'byAudience'  => $usersByAudience['rows'] ?? [], 
                    'activityFeed'=> $activityFeed['rows'] ?? [],   
                ]
            ]);

        } catch (Exception $e) {
            return $this->handleApiException('Realtime', $e);
        }
    }

    /**
     * Mengambil data HISTORIS dengan laporan tambahan dan rentang waktu dinamis.
     * Gunakan query param `?period=7days` atau `?period=90days`. Default 28 hari.
     */
    public function fetchHistoricalData(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');

            // BARU: Logika untuk rentang waktu dinamis
            $allowedPeriods = ['7days' => '7daysAgo', '28days' => '28daysAgo', '90days' => '90daysAgo'];
            $period = $request->query('period', '28days');
            $startDate = $allowedPeriods[$period] ?? $allowedPeriods['28days'];
            $dateRange = ['start_date' => $startDate, 'end_date' => 'today'];

            // Laporan yang sudah ada...
            $pageData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['pageTitle', 'pagePath'], ['screenPageViews', 'sessions', 'engagementRate', 'conversions'], 'screenPageViews');
            $geoData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['country', 'city'], ['activeUsers', 'newUsers', 'sessions', 'engagementRate', 'conversions'], 'activeUsers');
            $trafficSourceData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['sessionSourceMedium'], ['sessions', 'activeUsers', 'newUsers', 'engagementRate', 'conversions'], 'sessions');
            $techData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['deviceCategory', 'browser', 'operatingSystem'], ['sessions', 'activeUsers'], 'sessions');
            
            // BARU: Laporan Tren Harian (untuk grafik)
            $dailyTrendData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['date'], ['activeUsers', 'sessions'], null, 100); // limit lebih besar untuk data tren

            // BARU: Laporan Event Konversi
            $conversionEventData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['eventName'], ['conversions'], 'conversions');

            // BARU: Laporan Halaman Landing
            $landingPageData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['landingPage'], ['sessions', 'newUsers', 'engagementRate'], 'sessions');

            // Laporan Cohort tidak dipengaruhi rentang waktu dinamis
            $retentionData = $this->runCohortReport($analyticsData, $propertyId);

            // Summary tetap diambil dari data paling relevan
            $summaryTotals = $geoData['totals'];
            $summary = [
                'activeUsers'     => (int) ($summaryTotals['activeUsers'] ?? 0),
                'newUsers'        => (int) ($summaryTotals['newUsers'] ?? 0),
                'sessions'        => (int) ($summaryTotals['sessions'] ?? 0),
                'conversions'     => (int) ($summaryTotals['conversions'] ?? 0),
                'screenPageViews' => (int) ($pageData['totals']['screenPageViews'] ?? 0),
                'engagementRate'  => round((float)($summaryTotals['engagementRate'] ?? 0) * 100, 2) . '%',
                'averageSessionDuration' => gmdate("i:s", (int)($pageData['totals']['averageSessionDuration'] ?? 0)),
            ];

            return response()->json([
                'summary'   => $summary,
                'reports' => [
                    'dailyTrends'       => $dailyTrendData['rows'] ?? [],
                    'pages'             => $pageData['rows'] ?? [],
                    'landingPages'      => $landingPageData['rows'] ?? [],
                    'geography'         => $geoData['rows'] ?? [],
                    'trafficSources'    => $trafficSourceData['rows'] ?? [],
                    'conversionEvents'  => $conversionEventData['rows'] ?? [],
                    'technology'        => $techData['rows'] ?? [],
                    'userRetention'     => $retentionData ?? [],
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleApiException('Historis', $e);
        }
    }
    
    // --- HELPER FUNCTIONS ---

    private function runRealtimeReportHelper($analyticsData, $propertyId, array $dimensions, array $metrics): array
    {
        $dimensionObjects = array_map(fn($name) => new Dimension(['name' => $name]), $dimensions);
        $metricObjects = array_map(fn($name) => new Metric(['name' => $name]), $metrics);
        $request = new RunRealtimeReportRequest(['dimensions' => $dimensionObjects, 'metrics' => $metricObjects, 'limit' => 250]);
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
        $requestConfig = ['dateRanges' => [new GoogleDateRange($dateRangeConfig)],'dimensions' => $dimensionObjects,'metrics' => $metricObjects,'limit' => $limit,];
        if ($orderByMetric) { $requestConfig['orderBys'] = [new OrderBy(['metric' => new MetricOrderBy(['metric_name' => $orderByMetric]), 'desc' => true])]; }
        $request = new RunReportRequest($requestConfig);
        $response = $analyticsData->properties->runReport('properties/' . $propertyId, $request);
        $result = ['rows' => [], 'totals' => []];
        foreach ($response->getRows() as $row) {
            $rowData = [];
            foreach ($row->getDimensionValues() as $i => $dimValue) {
                // Format tanggal agar lebih mudah dibaca di frontend
                if ($dimensions[$i] === 'date') {
                    $rowData[$dimensions[$i]] = Carbon::createFromFormat('Ymd', $dimValue->getValue())->format('Y-m-d');
                } else {
                    $rowData[$dimensions[$i]] = $dimValue->getValue();
                }
            }
            foreach ($row->getMetricValues() as $i => $metricValue) { $rowData[$metrics[$i]] = $metricValue->getValue(); }
            $result['rows'][] = $rowData;
        }
        if ($response->getTotals() && count($response->getTotals()) > 0) {
            $totalsRow = $response->getTotals()[0];
            foreach ($totalsRow->getMetricValues() as $i => $metricValue) { $result['totals'][$metrics[$i]] = $metricValue->getValue(); }
        }
        if ($orderByMetric === null && !empty($result['rows'])) {
             // Urutkan data tren harian berdasarkan tanggal
            usort($result['rows'], fn($a, $b) => $a['date'] <=> $b['date']);
        }
        return $result;
    }

    private function runCohortReport($analyticsData, $propertyId): array
    {
        // ... Fungsi ini tidak perlu diubah, sudah sangat baik ...
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