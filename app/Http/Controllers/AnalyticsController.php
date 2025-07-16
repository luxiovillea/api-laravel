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
use Google\Service\AnalyticsData\Filter;
use Google\Service\AnalyticsData\StringFilter;
use Google\Service\AnalyticsData\FilterExpressionList;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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

    public function getFilterOptions()
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');
            $dateRange = ['start_date' => '30daysAgo', 'end_date' => 'today'];

            $options = [
                'countries' => $this->getUniqueDimensionValues($analyticsData, $propertyId, $dateRange, 'country'),
                'deviceCategories' => $this->getUniqueDimensionValues($analyticsData, $propertyId, $dateRange, 'deviceCategory'),
                'trafficSources' => $this->getUniqueDimensionValues($analyticsData, $propertyId, $dateRange, 'sessionSourceMedium'),
                'pageTitles' => $this->getUniqueDimensionValues($analyticsData, $propertyId, $dateRange, 'pageTitle'),
            ];

            return response()->json(array_filter($options));
        } catch (Exception $e) {
            return $this->handleApiException('Filter Options', $e);
        }
    }

    public function fetchHistoricalData(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');
            $dateRange = $this->parseDateRange($request);
            $filters = $this->parseFilters($request);
            
            $trafficSourceData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['sessionSourceMedium'], ['sessions', 'activeUsers', 'conversions'], 'sessions', 25, $filters);
            $detailedPageReport = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['pageTitle'], ['screenPageViews', 'activeUsers', 'averageSessionDuration'], 'screenPageViews', 10, $filters);
            $dailyTrendData = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, ['date'], ['activeUsers', 'sessions'], null, 100, $filters);
            $retentionData = $this->runCohortReport($analyticsData, $propertyId);

            $summaryTotals = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, [], ['activeUsers', 'sessions', 'engagementRate', 'bounceRate', 'averageSessionDuration'], null, 1, $filters)['totals'];
            $summary = [
                'activeUsers'     => (int) ($summaryTotals['activeUsers'] ?? 0),
                'sessions'        => (int) ($summaryTotals['sessions'] ?? 0),
                'engagementRate'  => round((float)($summaryTotals['engagementRate'] ?? 0) * 100, 2),
                'bounceRate'      => round((float)($summaryTotals['bounceRate'] ?? 0) * 100, 2),
                'averageSessionDuration' => gmdate("i\m s\s", (int)($summaryTotals['averageSessionDuration'] ?? 0)),
            ];

            return response()->json([
                'summary'   => $summary,
                'appliedFilters' => $filters,
                'dateRange' => $dateRange,
                'reports' => [
                    'dailyTrends'       => $dailyTrendData['rows'] ?? [],
                    'detailedPageReport'=> $detailedPageReport['rows'] ?? [],
                    'trafficSources'    => $trafficSourceData['rows'] ?? [],
                    'userRetention'     => $retentionData ?? [],
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleApiException('Historis', $e);
        }
    }

    public function getSegmentedData(Request $request)
    {
        try {
            $client = $this->getGoogleClient();
            $analyticsData = new AnalyticsData($client);
            $propertyId = env('GA_PROPERTY_ID');
            $dateRange = $this->parseDateRange($request);
            $baseFilters = $this->parseFilters($request);
            
            $segments = [
                'new_users' => ['newVsReturning' => 'new'],
                'returning_users' => ['newVsReturning' => 'returning'],
                'mobile_users' => ['deviceCategory' => 'mobile'],
                'desktop_users' => ['deviceCategory' => 'desktop'],
                'organic_traffic' => ['sessionDefaultChannelGroup' => 'Organic Search'],
            ];
            
            $segmentData = [];
            foreach ($segments as $segmentName => $segmentFilter) {
                $segmentFilters = array_merge($baseFilters, $segmentFilter);
                $data = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, [], ['activeUsers', 'sessions', 'engagementRate'], null, 1, $segmentFilters);
                
                $segmentData[$segmentName] = [
                    'activeUsers' => (int)($data['totals']['activeUsers'] ?? 0),
                    'sessions' => (int)($data['totals']['sessions'] ?? 0),
                    'engagementRate' => round(($data['totals']['engagementRate'] ?? 0) * 100, 2),
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

    // --- Helper Functions ---

    private function parseFilters(Request $request): array
    {
        $filters = $request->get('filters', []);
        return is_array($filters) ? array_filter($filters) : [];
    }

    private function parseDateRange(Request $request): array
    {
        $period = $request->get('period', '28daysAgo');
        if ($period === 'custom' && $request->has(['start_date', 'end_date'])) {
            return ['start_date' => $request->get('start_date'), 'end_date' => $request->get('end_date')];
        }
        $dateMap = ['7daysAgo' => '7daysAgo', '28daysAgo' => '28daysAgo', '90daysAgo' => '90daysAgo'];
        return ['start_date' => $dateMap[$period] ?? '28daysAgo', 'end_date' => 'today'];
    }

    private function getUniqueDimensionValues($analyticsData, $propertyId, array $dateRange, string $dimension, int $limit = 250): array
    {
        try {
            $result = $this->runHistoricalReport($analyticsData, $propertyId, $dateRange, [$dimension], ['activeUsers'], 'activeUsers', $limit);
            return array_map(fn($row) => $row[$dimension] ?? '(not set)', $result['rows'] ?? []);
        } catch (Exception $e) {
            return [];
        }
    }

    private function createFilterExpression(array $filters): ?FilterExpression
    {
        if (empty($filters)) return null;
        $filterExpressions = [];
        foreach ($filters as $dimension => $value) {
            if (empty($value)) continue;
            $filterExpressions[] = new FilterExpression(['filter' => new Filter(['field_name' => $dimension, 'string_filter' => new StringFilter(['match_type' => 'EXACT', 'value' => $value])])]);
        }
        if (count($filterExpressions) === 0) return null;
        if (count($filterExpressions) === 1) return $filterExpressions[0];
        return new FilterExpression(['and_group' => new FilterExpressionList(['expressions' => $filterExpressions])]);
    }

    private function runHistoricalReport($analyticsData, $propertyId, array $dateRangeConfig, array $dimensions, array $metrics, ?string $orderByMetric = null, int $limit = 25, array $filters = []): array
    {
        $requestConfig = [
            'dateRanges' => [new GoogleDateRange($dateRangeConfig)],
            'dimensions' => array_map(fn($name) => new Dimension(['name' => $name]), $dimensions),
            'metrics' => array_map(fn($name) => new Metric(['name' => $name]), $metrics),
            'limit' => $limit,
            'metricAggregations' => ['TOTAL']
        ];
        if ($filterExpression = $this->createFilterExpression($filters)) {
            $requestConfig['dimension_filter'] = $filterExpression;
        }
        if ($orderByMetric) { 
            $requestConfig['orderBys'] = [new OrderBy(['metric' => new MetricOrderBy(['metric_name' => $orderByMetric]), 'desc' => true])]; 
        }
        $response = $analyticsData->properties->runReport('properties/' . $propertyId, new RunReportRequest($requestConfig));
        $result = ['rows' => [], 'totals' => []];
        foreach ($response->getRows() as $row) {
            $rowData = [];
            foreach ($row->getDimensionValues() as $i => $dimValue) {
                $rowData[$dimensions[$i]] = ($dimensions[$i] === 'date') ? Carbon::createFromFormat('Ymd', $dimValue->getValue())->format('Y-m-d') : $dimValue->getValue();
            }
            foreach ($row->getMetricValues() as $i => $metricValue) { 
                $rowData[$metrics[$i]] = (float)$metricValue->getValue();
            }
            $result['rows'][] = $rowData;
        }
        if (count($response->getTotals()) > 0) {
            foreach ($response->getTotals()[0]->getMetricValues() as $i => $metricValue) { 
                $result['totals'][$metrics[$i]] = (float)$metricValue->getValue();
            }
        }
        return $result;
    }

    private function runCohortReport($analyticsData, $propertyId): array
    {
        // PERBAIKAN UTAMA: Mengganti nama cohort agar tidak diawali "cohort_"
        $cohortSpec = new CohortSpec([
            'cohorts' => [ 
                new \Google\Service\AnalyticsData\Cohort([
                    'name' => 'week_0', // NAMA DIGANTI
                    'dimension' => 'firstTouchDate', 
                    'dateRange' => new GoogleDateRange(['start_date' => '7daysAgo', 'end_date' => 'today'])
                ]), 
                new \Google\Service\AnalyticsData\Cohort([
                    'name' => 'week_1', // NAMA DIGANTI
                    'dimension' => 'firstTouchDate', 
                    'dateRange' => new GoogleDateRange(['start_date' => '14daysAgo', 'end_date' => '8daysAgo'])
                ]), 
            ], 
            'cohortsRange' => new \Google\Service\AnalyticsData\CohortsRange(['granularity' => 'WEEKLY', 'start_offset' => 0, 'end_offset' => 4]), 
            'cohortReportSettings' => new \Google\Service\AnalyticsData\CohortReportSettings(['accumulate' => false]), 
        ]);
        
        $request = new RunReportRequest([
            'cohortSpec' => $cohortSpec, 
            'dimensions' => [new Dimension(['name' => 'cohort']), new Dimension(['name' => 'cohortNthWeek'])], 
            'metrics' => [new Metric(['name' => 'cohortActiveUsers'])], 
        ]);
        
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
        Log::error("GA API Error in {$context}: " . $message, ['exception' => $e]);
        return response()->json(['error' => "Gagal mengambil data {$context}: " . $message], 500);
    }
}