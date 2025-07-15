<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Client;
use Google\Service\AnalyticsData;
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
    // getGoogleClient() dan handleApiException() tidak berubah, tetap sama.
    private function getGoogleClient(): Client { /* ... kode sama seperti sebelumnya ... */ }
    private function handleApiException(string $context, Exception $e): \Illuminate\Http\JsonResponse { /* ... kode sama seperti sebelumnya ... */ }

    /**
     * Mengambil data HISTORIS yang diformat persis seperti laporan "Pages and Screens".
     *
     * URL Contoh:
     * - GET /api/analytics/historical/123456789
     * - GET /api/analytics/historical/123456789?period=7days
     * - GET /api/analytics/historical/123456789?pageTitle=Byte%20Cafe
     */
    public function fetchHistoricalData(Request $request, string $propertyId)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);

            // 1. Tentukan Rentang Waktu
            $allowedPeriods = ['7days' => '7daysAgo', '28days' => '28daysAgo', '90days' => '90daysAgo'];
            $period = $request->query('period', '28days');
            $startDate = $allowedPeriods[$period] ?? $allowedPeriods['28days'];
            $dateRange = ['start_date' => $startDate, 'end_date' => 'today'];

            // 2. Buat Filter Dinamis (jika ada)
            $filterExpression = $this->buildFilterExpression($request);

            // 3. Request Laporan ke Google dengan Metrik yang Tepat
            $rawReport = $this->runHistoricalReport(
                $analyticsData,
                $propertyId,
                $dateRange,
                ['unifiedScreenName'], // Dimensi: Page title and screen class
                [ // Metrik yang dibutuhkan untuk kalkulasi
                    'screenPageViews',        // => Views
                    'activeUsers',            // => Active users
                    'userEngagementDuration', // => Untuk menghitung Average engagement time
                    'eventCount',             // => Event count
                    'conversions',            // => Key events (sebelumnya)
                    'totalRevenue'            // => Total revenue
                ],
                'screenPageViews', // Urutkan berdasarkan Views (paling banyak dilihat)
                50,
                $filterExpression
            );

            // 4. Proses dan Format Ulang Laporan Mentah
            $formattedReport = $this->formatPagesAndScreensReport($rawReport);

            // 5. Kirim Respons Final
            return response()->json([
                'metadata' => [
                    'propertyId' => $propertyId,
                    'period' => $period,
                    'filtersApplied' => $request->only(['country', 'city', 'pageTitle', 'sourceMedium']),
                    'reportName' => 'Pages and Screens Detailed Report'
                ],
                'report' => $formattedReport
            ]);

        } catch (Exception $e) {
            return $this->handleApiException("Historis (Property: {$propertyId})", $e);
        }
    }

    /**
     * FUNGSI BARU: Mengubah data mentah dari Google menjadi laporan yang siap pakai.
     * Di sinilah semua "keajaiban" (kalkulasi dan format) terjadi.
     */
    private function formatPagesAndScreensReport(array $rawReport): array
    {
        $formatted = [
            // Header ini sangat berguna untuk frontend saat membuat tabel
            'headers' => [
                'pageTitle' => 'Page title and screen class',
                'views' => 'Views',
                'activeUsers' => 'Active users',
                'viewsPerUser' => 'Views per active user',
                'avgEngagementTime' => 'Average engagement time',
                'eventCount' => 'Event count',
                'keyEvents' => 'Key events',
                'totalRevenue' => 'Total revenue',
            ],
            'rows' => [],
            'totals' => []
        ];

        // Proses setiap baris data
        foreach ($rawReport['rows'] as $rawRow) {
            $views = (float)($rawRow['screenPageViews'] ?? 0);
            $users = (float)($rawRow['activeUsers'] ?? 0);
            $engagementSeconds = (float)($rawRow['userEngagementDuration'] ?? 0);

            $formatted['rows'][] = [
                'pageTitle' => $rawRow['unifiedScreenName'] ?? '(not set)',
                'views' => (int)$views,
                'activeUsers' => (int)$users,
                'viewsPerUser' => $users > 0 ? round($views / $users, 2) : 0,
                'avgEngagementTime' => $this->formatDuration($users > 0 ? $engagementSeconds / $users : 0),
                'eventCount' => (int)($rawRow['eventCount'] ?? 0),
                'keyEvents' => (int)($rawRow['conversions'] ?? 0),
                'totalRevenue' => (float)($rawRow['totalRevenue'] ?? 0),
            ];
        }

        // Proses baris Total
        if (!empty($rawReport['totals'])) {
            $totalViews = (float)($rawReport['totals']['screenPageViews'] ?? 0);
            $totalUsers = (float)($rawReport['totals']['activeUsers'] ?? 0);
            $totalEngagementSeconds = (float)($rawReport['totals']['userEngagementDuration'] ?? 0);
            
            $formatted['totals'] = [
                'pageTitle' => 'Total',
                'views' => (int)$totalViews,
                'activeUsers' => (int)$totalUsers,
                'viewsPerUser' => $totalUsers > 0 ? round($totalViews / $totalUsers, 2) : 0,
                'avgEngagementTime' => $this->formatDuration($totalUsers > 0 ? $totalEngagementSeconds / $totalUsers : 0),
                'eventCount' => (int)($rawReport['totals']['eventCount'] ?? 0),
                'keyEvents' => (int)($rawReport['totals']['conversions'] ?? 0),
                'totalRevenue' => (float)($rawReport['totals']['totalRevenue'] ?? 0),
            ];
        }

        return $formatted;
    }

    /**
     * FUNGSI HELPER BARU: Mengubah detik menjadi format "Xm Ys".
     */
    private function formatDuration(float $seconds): string
    {
        if ($seconds < 1) {
            return '0m 0s';
        }
        $minutes = floor($seconds / 60);
        $remainingSeconds = round($seconds % 60);
        return "{$minutes}m {$remainingSeconds}s";
    }

    /**
     * HELPER: Fungsi untuk membuat filter dari query string.
     * (Tidak berubah, sudah benar)
     */
    private function buildFilterExpression(Request $request): ?FilterExpression
    {
        $filters = [];
        $supportedFilters = [
            'country' => 'country',
            'city' => 'city',
            'pageTitle' => 'unifiedScreenName', // Diubah agar cocok dengan dimensi laporan
            'sourceMedium' => 'sessionSourceMedium'
        ];

        foreach ($supportedFilters as $queryParam => $dimensionName) {
            if ($request->has($queryParam)) {
                $filters[] = new Filter([
                    'field_name' => $dimensionName,
                    'string_filter' => new StringFilter([
                        'match_type' => 'CONTAINS',
                        'value' => $request->query($queryParam),
                        'case_sensitive' => false
                    ])
                ]);
            }
        }

        if (empty($filters)) return null;

        return new FilterExpression(['and_group' => new FilterExpressionList(['expressions' => $filters])]);
    }

    /**
     * HELPER: Menjalankan request ke Google Analytics.
     * (Sedikit disederhanakan, tapi intinya sama)
     */
    private function runHistoricalReport($analyticsData, $propertyId, array $dateRangeConfig, array $dimensions, array $metrics, ?string $orderByMetric = null, int $limit = 25, ?FilterExpression $filterExpression = null): array
    {
        $request = new RunReportRequest([
            'property' => 'properties/' . $propertyId,
            'dateRanges' => [new GoogleDateRange($dateRangeConfig)],
            'dimensions' => array_map(fn($name) => new Dimension(['name' => $name]), $dimensions),
            'metrics' => array_map(fn($name) => new Metric(['name' => $name]), $metrics),
            'limit' => $limit,
            'metricAggregations' => ['TOTAL'] // Penting untuk mendapatkan baris Total
        ]);

        if ($orderByMetric) {
            $request->setOrderBys([new OrderBy(['metric' => new MetricOrderBy(['metric_name' => $orderByMetric]), 'desc' => true])]);
        }
        if ($filterExpression) {
            $request->setDimensionFilter($filterExpression);
        }

        $response = $analyticsData->properties->runReport('properties/' . $propertyId, $request);
        
        // Ubah response menjadi array yang lebih mudah diolah
        $result = ['rows' => [], 'totals' => []];
        foreach ($response->getRows() as $row) {
            $rowData = [];
            foreach ($row->getDimensionValues() as $i => $dimValue) {
                $rowData[$dimensions[$i]] = $dimValue->getValue();
            }
            foreach ($row->getMetricValues() as $i => $metricValue) {
                $rowData[$metrics[$i]] = $metricValue->getValue();
            }
            $result['rows'][] = $rowData;
        }

        if ($response->getTotals() && count($response->getTotals()) > 0) {
            $totalsRow = $response->getTotals()[0];
            foreach ($totalsRow->getMetricValues() as $i => $metricValue) {
                $result['totals'][$metrics[$i]] = $metricValue->getValue();
            }
        }
        return $result;
    }
}