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
use Google\Service\AnalyticsData\FilterExpressionList;
use Google\Service\AnalyticsData\Filter;
use Google\Service\AnalyticsData\Filter\StringFilter;
use Exception;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Menginisialisasi Google Client.
     * (Fungsi ini tidak perlu diubah)
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
     * Mengambil data REALTIME.
     * DIUBAH: Menerima $propertyId dari URL.
     */
    public function fetchRealtimeData(string $propertyId)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            // $propertyId diambil dari parameter fungsi, bukan env lagi.

            $usersByPage = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['unifiedScreenName', 'deviceCategory'], ['activeUsers']);
            $usersByLocation = $this->runRealtimeReportHelper($analyticsData, $propertyId, ['country', 'city'], ['activeUsers']);
            
            $totalActiveUsers = collect($usersByLocation['rows'] ?? [])->sum('activeUsers');

            return response()->json([
                'totalActiveUsers' => (int) $totalActiveUsers,
                'reports' => [
                    'byPage'      => $usersByPage['rows'] ?? [],
                    'byLocation'  => $usersByLocation['rows'] ?? [],
                ]
            ]);

        } catch (Exception $e) {
            return $this->handleApiException("Realtime (Property: {$propertyId})", $e);
        }
    }

    /**
     * Mengambil data HISTORIS dengan filter dinamis dan laporan detail.
     * DIUBAH:
     * 1. Menerima $propertyId dari URL.
     * 2. Menerima filter dari query string (e.g., ?country=Indonesia).
     * 3. Membuat laporan "Pages and Screens" yang detail sesuai screenshot.
     */
    public function fetchHistoricalData(Request $request, string $propertyId)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);

            // Logika rentang waktu dinamis (tetap sama)
            $allowedPeriods = ['7days' => '7daysAgo', '28days' => '28daysAgo', '90days' => '90daysAgo'];
            $period = $request->query('period', '28days');
            $startDate = $allowedPeriods[$period] ?? $allowedPeriods['28days'];
            $dateRange = ['start_date' => $startDate, 'end_date' => 'today'];

            // BARU: Membuat filter berdasarkan query parameters
            $filterExpression = $this->buildFilterExpression($request);

            // BARU & DISESUAIKAN: Laporan Pages and Screens yang detail sesuai screenshot
            $pagesAndScreensData = $this->runHistoricalReport(
                $analyticsData,
                $propertyId,
                $dateRange,
                ['unifiedScreenName'], // Dimensi: Page title and screen class
                [ // Metrik sesuai screenshot
                    'screenPageViews',      // Views
                    'activeUsers',          // Active users
                    'averageSessionDuration', // Terkait Average engagement time
                    'eventCount',           // Event count
                    'conversions',          // Key events
                    'totalRevenue'          // Total revenue
                ],
                'screenPageViews', // Urutkan berdasarkan Views
                50, // Limit baris
                $filterExpression  // Terapkan filter dinamis
            );
            
            // BARU: Menambahkan metrik kalkulasi "Views per Active User"
            foreach ($pagesAndScreensData['rows'] as &$row) { // Gunakan & untuk modifikasi langsung
                $views = (float)($row['screenPageViews'] ?? 0);
                $users = (float)($row['activeUsers'] ?? 0);
                $row['viewsPerUser'] = $users > 0 ? round($views / $users, 2) : 0;
            }
            unset($row); // Hapus referensi setelah loop selesai


            // Laporan lain tetap bisa dibuat jika dibutuhkan
            $geoData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['country', 'city'], ['activeUsers', 'newUsers', 'sessions'], 'activeUsers', 25, $filterExpression);
            $dailyTrendData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['date'], ['activeUsers', 'sessions'], null, 100, $filterExpression);

            return response()->json([
                'metadata' => [
                    'propertyId' => $propertyId,
                    'period' => $period,
                    'filtersApplied' => $request->only(['country', 'city', 'pageTitle', 'sourceMedium'])
                ],
                // Ini adalah laporan utama yang Anda minta, dengan data per baris yang detail
                'pagesAndScreensReport' => $pagesAndScreensData,
                // Laporan tambahan bisa disertakan di sini
                'otherReports' => [
                    'dailyTrends' => $dailyTrendData['rows'] ?? [],
                    'geography'   => $geoData['rows'] ?? [],
                ]
            ]);

        } catch (Exception $e) {
            return $this->handleApiException("Historis (Property: {$propertyId})", $e);
        }
    }

    // --- HELPER FUNCTIONS ---

    /**
     * BARU: Fungsi untuk membuat objek FilterExpression dari Request.
     */
    private function buildFilterExpression(Request $request): ?FilterExpression
    {
        $filters = [];
        $supportedFilters = [
            'country' => 'country',
            'city' => 'city',
            'pageTitle' => 'pageTitle',
            'sourceMedium' => 'sessionSourceMedium'
        ];

        foreach ($supportedFilters as $queryParam => $dimensionName) {
            if ($request->has($queryParam)) {
                $filters[] = new Filter([
                    'field_name' => $dimensionName,
                    'string_filter' => new StringFilter([
                        'match_type' => 'CONTAINS', // Bisa juga 'EXACT', 'BEGINS_WITH', etc.
                        'value' => $request->query($queryParam),
                        'case_sensitive' => false
                    ])
                ]);
            }
        }

        if (empty($filters)) {
            return null;
        }

        // Menggabungkan semua filter dengan logika "AND"
        return new FilterExpression([
            'and_group' => new FilterExpressionList(['expressions' => $filters])
        ]);
    }

    private function runRealtimeReportHelper($analyticsData, $propertyId, array $dimensions, array $metrics): array
    {
        // (Fungsi ini tidak perlu diubah)
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

    /**
     * DIUBAH: Menerima parameter $filterExpression untuk filtering.
     */
    private function runHistoricalReport($analyticsData, $propertyId, array $dateRangeConfig, array $dimensions, array $metrics, ?string $orderByMetric = null, int $limit = 25, ?FilterExpression $filterExpression = null): array
    {
        $dimensionObjects = array_map(fn($name) => new Dimension(['name' => $name]), $dimensions);
        $metricObjects = array_map(fn($name) => new Metric(['name' => $name]), $metrics);

        $requestConfig = [
            'dateRanges' => [new GoogleDateRange($dateRangeConfig)],
            'dimensions' => $dimensionObjects,
            'metrics' => $metricObjects,
            'limit' => $limit,
            // BARU: Menambahkan metrik aggregate (total) ke request
            'metricAggregations' => ['TOTAL'] 
        ];

        if ($orderByMetric) {
            $requestConfig['orderBys'] = [new OrderBy(['metric' => new MetricOrderBy(['metric_name' => $orderByMetric]), 'desc' => true])];
        }

        // BARU: Menambahkan filter ke request jika ada
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
                $rowData[$dimName] = ($dimName === 'date')
                    ? Carbon::createFromFormat('Ymd', $dimValue->getValue())->format('Y-m-d')
                    : $dimValue->getValue();
            }
            foreach ($row->getMetricValues() as $i => $metricValue) {
                $rowData[$metrics[$i]] = $metricValue->getValue();
            }
            $result['rows'][] = $rowData;
        }

        // Mengambil data total dari response
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

    // Fungsi runCohortReport dan handleApiException tidak perlu diubah.
    // ... (salin fungsi runCohortReport dan handleApiException dari kode lama Anda ke sini) ...
    
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