<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Client;
use Google\Service\AnalyticsData;
use Google\Service\AnalyticsData\DateRange;
use Google\Service\AnalyticsData\Dimension;
use Google\Service\AnalyticsData\Metric;
use Google\Service\AnalyticsData\RunReportRequest;
use Google\Service\AnalyticsData\OrderBy;
use Google\Service\AnalyticsData\MetricOrderBy;
use Exception;

class AnalyticsController extends Controller
{
    /**
     * Endpoint FINAL untuk mengambil laporan "Pages and Screens".
     *
     * @param Request $request
     * @param string $propertyId ID Properti Google Analytics
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReport(Request $request, string $propertyId)
    {
        try {
            // 1. Inisialisasi Google Client
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);

            // 2. Tentukan Rentang Waktu (Default 28 hari)
            $period = $request->query('period', '28days');
            $allowedPeriods = ['7days' => '7daysAgo', '28days' => '28daysAgo', '90days' => '90daysAgo'];
            $startDate = $allowedPeriods[$period] ?? $allowedPeriods['28days'];
            $dateRange = new DateRange(['start_date' => $startDate, 'end_date' => 'today']);

            // 3. Buat Request ke Google Analytics
            $gaRequest = new RunReportRequest([
                'property' => 'properties/' . $propertyId,
                'dateRanges' => [$dateRange],
                'dimensions' => [new Dimension(['name' => 'unifiedScreenName'])],
                'metrics' => [
                    new Metric(['name' => 'screenPageViews']),
                    new Metric(['name' => 'activeUsers']),
                    new Metric(['name' => 'userEngagementDuration']),
                    new Metric(['name' => 'eventCount']),
                    new Metric(['name' => 'conversions']), // Di GA4, ini menggantikan "Key Events"
                    new Metric(['name' => 'totalRevenue']),
                ],
                'orderBys' => [
                    new OrderBy([
                        'metric' => new MetricOrderBy(['metric_name' => 'screenPageViews']),
                        'desc' => true,
                    ]),
                ],
                'limit' => 100,
                'metricAggregations' => ['TOTAL'],
            ]);

            // 4. Jalankan Laporan
            $response = $analyticsData->properties->runReport('properties/' . $propertyId, $gaRequest);

            // 5. Format hasil menjadi JSON yang rapi
            return $this->formatSuccessResponse($response, $propertyId, $period);

        } catch (Exception $e) {
            return $this->formatErrorResponse($e, $propertyId);
        }
    }

    /**
     * Helper untuk memformat respons sukses.
     */
    private function formatSuccessResponse($response, string $propertyId, string $period): \Illuminate\Http\JsonResponse
    {
        $reportData = [
            'rows' => [],
            'totals' => [],
        ];

        // Format setiap baris data
        foreach ($response->getRows() as $row) {
            $metrics = $row->getMetricValues();
            $views = (float)($metrics[0]->getValue() ?? 0);
            $users = (float)($metrics[1]->getValue() ?? 0);
            $engagementSeconds = (float)($metrics[2]->getValue() ?? 0);

            $reportData['rows'][] = [
                'pageTitle' => $row->getDimensionValues()[0]->getValue(),
                'views' => (int) $views,
                'activeUsers' => (int) $users,
                'viewsPerUser' => $users > 0 ? round($views / $users, 2) : 0,
                'avgEngagementTime' => $this->formatDuration($users > 0 ? $engagementSeconds / $users : 0),
                'eventCount' => (int)($metrics[3]->getValue() ?? 0),
                'keyEvents' => (int)($metrics[4]->getValue() ?? 0),
                'totalRevenue' => (float)($metrics[5]->getValue() ?? 0),
            ];
        }

        // Format baris total
        if ($response->getTotals() && count($response->getTotals()) > 0) {
            $totalMetrics = $response->getTotals()[0]->getMetricValues();
            $totalViews = (float)($totalMetrics[0]->getValue() ?? 0);
            $totalUsers = (float)($totalMetrics[1]->getValue() ?? 0);
            $totalEngagementSeconds = (float)($totalMetrics[2]->getValue() ?? 0);

            $reportData['totals'] = [
                'views' => (int) $totalViews,
                'activeUsers' => (int) $totalUsers,
                'viewsPerUser' => $totalUsers > 0 ? round($totalViews / $totalUsers, 2) : 0,
                'avgEngagementTime' => $this->formatDuration($totalUsers > 0 ? $totalEngagementSeconds / $totalUsers : 0),
                'eventCount' => (int)($totalMetrics[3]->getValue() ?? 0),
                'keyEvents' => (int)($totalMetrics[4]->getValue() ?? 0),
                'totalRevenue' => (float)($totalMetrics[5]->getValue() ?? 0),
            ];
        }

        return response()->json([
            'status' => 'success',
            'metadata' => ['propertyId' => $propertyId, 'period' => $period],
            'report' => $reportData,
        ]);
    }

    /**
     * Helper untuk mengubah detik menjadi format "Xm Ys".
     */
    private function formatDuration(float $totalSeconds): string
    {
        if ($totalSeconds < 1) return '0m 0s';
        $minutes = floor($totalSeconds / 60);
        $seconds = round($totalSeconds % 60);
        return "{$minutes}m {$seconds}s";
    }

    /**
     * Helper untuk menginisialisasi Google Client.
     */
    private function getGoogleClient(): Client
    {
        $credentialsJson = env('GOOGLE_CREDENTIALS_JSON');
        if (empty($credentialsJson)) {
            throw new Exception("Variabel lingkungan GOOGLE_CREDENTIALS_JSON tidak ada.");
        }
        $authConfig = json_decode($credentialsJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Isi GOOGLE_CREDENTIALS_JSON bukan JSON yang valid.");
        }
        $client = new Client();
        $client->setAuthConfig($authConfig);
        $client->addScope('https://www.googleapis.com/auth/analytics.readonly');
        return $client;
    }

    /**
     * Helper untuk menangani error.
     */
    private function formatErrorResponse(Exception $e, string $propertyId): \Illuminate\Http\JsonResponse
    {
        $decodedMessage = json_decode($e->getMessage(), true);
        $errorMessage = (json_last_error() === JSON_ERROR_NONE && isset($decodedMessage['error']['message']))
            ? $decodedMessage['error']['message']
            : $e->getMessage();
        
        return response()->json([
            'status' => 'error',
            'message' => 'Gagal mengambil data dari Google Analytics.',
            'details' => $errorMessage,
            'propertyId' => $propertyId,
        ], 500);
    }
}