<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
    <!-- Impor Chart.js dari CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-color: #f4f7f9;
            --card-bg-color: #ffffff;
            --text-color: #333a45;
            --heading-color: #1e222b;
            --subtle-text-color: #6c757d;
            --border-color: #e9ecef;
            --primary-color: #007bff;
            --primary-color-light: #cfe2ff;
            --shadow-color: rgba(0, 0, 0, 0.05);
            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        body {
            font-family: var(--font-family);
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: var(--bg-color);
            color: var(--text-color);
        }

        .container {
            max-width: 1400px;
            margin: auto;
        }
        
        h1, h2, h3 {
            color: var(--heading-color);
            font-weight: 600;
        }
        h2 { border-bottom: 2px solid var(--border-color); padding-bottom: 10px; margin-top: 40px; }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .grid-4 {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }

        .card {
            background: var(--card-bg-color);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px var(--shadow-color);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
        }
        
        .card.full-width {
             grid-column: 1 / -1;
        }

        .summary-value {
            font-size: 2.2em;
            font-weight: 700;
            color: var(--primary-color);
            margin: 5px 0 0 0;
        }

        .loading {
            color: var(--subtle-text-color);
            font-style: italic;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            text-align: left;
            padding: 12px 10px;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8em;
            color: var(--subtle-text-color);
        }
        tr:last-child td { border-bottom: none; }
        
        .period-filters {
            margin-bottom: 20px;
        }
        .period-filters button {
            background-color: var(--card-bg-color);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            margin-right: 10px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        .period-filters button.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .realtime-live-dot {
            height: 10px;
            width: 10px;
            background-color: #28a745;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 1.5s infinite;
            margin-right: 8px;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }
        
        .cohort-grid { font-size: 0.9em; }
        .cohort-cell {
            padding: 6px;
            text-align: center;
            border-radius: 4px;
            color: white;
            min-width: 60px;
        }
        
        .error-message {
            color: #dc3545;
            background: #f8d7da;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #f5c6cb;
        }

    </style>
</head>
<body>

    <div class="container">
        <h1>Dashboard Google Analytics</h1>

        <!-- =======================
             REALTIME SECTION
        ======================== -->
        <h2><span class="realtime-live-dot"></span>Realtime</h2>
        <div class="grid grid-4">
             <div class="card">
                <h3>Pengguna Online</h3>
                <p id="realtime-users" class="summary-value loading">0</p>
            </div>
             <div class="card">
                <h3>Halaman Aktif Teratas</h3>
                <div id="realtime-pages" class="loading">Memuat...</div>
            </div>
             <div class="card">
                <h3>Lokasi Aktif Teratas</h3>
                <div id="realtime-locations" class="loading">Memuat...</div>
            </div>
             <div class="card">
                <h3>Feed Aktivitas</h3>
                <div id="realtime-feed" class="loading">Memuat...</div>
            </div>
        </div>

        <!-- =======================
             HISTORICAL SECTION
        ======================== -->
        <h2>Data Historis
            <div id="period-filters" class="period-filters" style="display: inline-block; margin-left: 20px;">
                <button data-period="7days">7 Hari</button>
                <button data-period="28days" class="active">28 Hari</button>
                <button data-period="90days">90 Hari</button>
            </div>
        </h2>
        
        <div id="historical-content">
            <!-- Summary Cards -->
            <div class="grid grid-4" id="summary-cards">
                <!-- Data akan diisi oleh JavaScript -->
            </div>

            <!-- Chart and Cohort -->
            <div class="grid">
                 <div class="card full-width">
                    <h3>Tren Pengguna & Sesi Harian</h3>
                    <canvas id="daily-trends-chart"></canvas>
                </div>
                 <div class="card full-width">
                    <h3>Retensi Pengguna Mingguan (Cohort)</h3>
                    <div id="cohort-table" class="loading">Memuat...</div>
                </div>
            </div>

            <!-- Detailed Tables -->
            <div class="grid">
                <div class="card">
                    <h3>Halaman Teratas</h3>
                    <div id="pages-table" class="loading">Memuat...</div>
                </div>
                <div class="card">
                    <h3>Halaman Landing Teratas</h3>
                    <div id="landing-pages-table" class="loading">Memuat...</div>
                </div>
            </div>
            <div class="grid">
                <div class="card">
                    <h3>Sumber Lalu Lintas</h3>
                    <div id="traffic-sources-table" class="loading">Memuat...</div>
                </div>
                <div class="card">
                    <h3>Lokasi Pengunjung</h3>
                    <div id="geography-table" class="loading">Memuat...</div>
                </div>
            </div>
             <div class="grid">
                <div class="card">
                    <h3>Event Konversi</h3>
                    <div id="conversions-table" class="loading">Memuat...</div>
                </div>
                <div class="card">
                    <h3>Teknologi</h3>
                    <div id="technology-table" class="loading">Memuat...</div>
                </div>
            </div>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let dailyChartInstance;
            let currentPeriod = '28days';

            // =======================
            // HELPER FUNCTIONS
            // =======================
            function renderTable(elementId, data, columns, noDataMessage = 'Tidak ada data tersedia.') {
                const container = document.getElementById(elementId);
                if (!data || data.length === 0) {
                    container.innerHTML = `<p class="loading">${noDataMessage}</p>`;
                    return;
                }

                let tableHtml = '<table><thead><tr>';
                columns.forEach(col => tableHtml += `<th>${col.header}</th>`);
                tableHtml += '</tr></thead><tbody>';

                data.forEach(row => {
                    tableHtml += '<tr>';
                    columns.forEach(col => {
                        let value = row[col.key] || 'N/A';
                        if (col.formatter) {
                            value = col.formatter(value);
                        }
                        tableHtml += `<td title="${value}">${value}</td>`;
                    });
                    tableHtml += '</tr>';
                });

                tableHtml += '</tbody></table>';
                container.innerHTML = tableHtml;
            }
            
            function renderSummaryCard(id, title, value) {
                return `
                    <div class="card">
                        <h3>${title}</h3>
                        <p id="${id}" class="summary-value">${value}</p>
                    </div>`;
            }
            
             function getColorForPercentage(p) {
                if (p > 50) return '#28a745';
                if (p > 25) return '#17a2b8';
                if (p > 10) return '#007bff';
                if (p > 5) return '#6c757d';
                if (p > 0) return '#343a40';
                return 'transparent';
            }


            // =======================
            // REALTIME DATA FETCHER
            // =======================
            function fetchRealtimeData() {
                fetch('/api/analytics-data/realtime')
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Realtime Error:', data.error);
                            document.getElementById('realtime-users').innerText = 'Error';
                            return;
                        }
                        
                        document.getElementById('realtime-users').innerText = data.totalActiveUsers;
                        
                        renderTable('realtime-pages', data.reports.byPage, [
                            { header: 'Halaman', key: 'unifiedScreenName' },
                            { header: 'Pengguna', key: 'activeUsers' }
                        ], 'Tidak ada pengguna aktif.');

                        renderTable('realtime-locations', data.reports.byLocation, [
                            { header: 'Lokasi', key: 'city', formatter: (val, row) => `${row.city}, ${row.country}` },
                            { header: 'Pengguna', key: 'activeUsers' }
                        ], 'Tidak ada pengguna aktif.');
                        
                        renderTable('realtime-feed', data.reports.activityFeed, [
                             { header: 'Menit Lalu', key: 'minutesAgo' },
                             { header: 'Aktivitas', key: 'unifiedScreenName' },
                        ], 'Tidak ada aktivitas.');
                    })
                    .catch(error => console.error('Gagal mengambil data realtime:', error));
            }


            // =======================
            // HISTORICAL DATA FETCHER
            // =======================
            function fetchHistoricalData(period) {
                // Tampilkan loading state
                document.getElementById('historical-content').style.opacity = '0.5';
                
                fetch(`/api/analytics-data/historical?period=${period}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Historical Error:', data.error);
                            document.getElementById('historical-content').innerHTML = `<p class="error-message">Gagal memuat data: ${data.error}</p>`;
                            return;
                        }
                        
                        // Render Summary
                        const summaryContainer = document.getElementById('summary-cards');
                        summaryContainer.innerHTML = 
                            renderSummaryCard('summary-active-users', 'Pengguna Aktif', data.summary.activeUsers.toLocaleString()) +
                            renderSummaryCard('summary-new-users', 'Pengguna Baru', data.summary.newUsers.toLocaleString()) +
                            renderSummaryCard('summary-sessions', 'Sesi', data.summary.sessions.toLocaleString()) +
                            renderSummaryCard('summary-page-views', 'Page Views', data.summary.screenPageViews.toLocaleString()) +
                            renderSummaryCard('summary-conversions', 'Konversi', data.summary.conversions.toLocaleString()) +
                            renderSummaryCard('summary-engagement', 'Engagement Rate', data.summary.engagementRate) +
                            renderSummaryCard('summary-duration', 'Avg. Durasi Sesi', data.summary.averageSessionDuration);
                        
                        // Render Chart
                        renderDailyChart(data.reports.dailyTrends);

                        // Render Tables
                        renderTable('pages-table', data.reports.pages, [
                            { header: 'Judul Halaman', key: 'pageTitle' },
                            { header: 'Views', key: 'screenPageViews', formatter: v => parseInt(v).toLocaleString() }
                        ]);
                        renderTable('landing-pages-table', data.reports.landingPages, [
                            { header: 'URL Halaman Landing', key: 'landingPage' },
                            { header: 'Sesi', key: 'sessions', formatter: v => parseInt(v).toLocaleString() }
                        ]);
                        renderTable('traffic-sources-table', data.reports.trafficSources, [
                            { header: 'Sumber', key: 'sessionSourceMedium' },
                            { header: 'Sesi', key: 'sessions', formatter: v => parseInt(v).toLocaleString() }
                        ]);
                        renderTable('geography-table', data.reports.geography, [
                            { header: 'Negara', key: 'country' },
                            { header: 'Pengguna', key: 'activeUsers', formatter: v => parseInt(v).toLocaleString() }
                        ]);
                        renderTable('conversions-table', data.reports.conversionEvents, [
                            { header: 'Nama Event', key: 'eventName' },
                            { header: 'Jumlah Konversi', key: 'conversions', formatter: v => parseInt(v).toLocaleString() }
                        ]);
                        renderTable('technology-table', data.reports.technology, [
                            { header: 'Browser', key: 'browser' },
                             { header: 'OS', key: 'operatingSystem' },
                            { header: 'Sesi', key: 'sessions', formatter: v => parseInt(v).toLocaleString() }
                        ]);
                        
                        // Render Cohort Table
                        renderCohortTable(data.reports.userRetention);

                    })
                    .catch(error => {
                        console.error('Gagal mengambil data historis:', error)
                        document.getElementById('historical-content').innerHTML = `<p class="error-message">Terjadi kesalahan jaringan.</p>`;
                    })
                    .finally(() => {
                        document.getElementById('historical-content').style.opacity = '1';
                    });
            }


            // =======================
            // RENDER FUNCTIONS
            // =======================
            function renderDailyChart(data) {
                const ctx = document.getElementById('daily-trends-chart').getContext('2d');
                if (dailyChartInstance) {
                    dailyChartInstance.destroy();
                }

                const labels = data.map(row => row.date);
                const usersData = data.map(row => row.activeUsers);
                const sessionsData = data.map(row => row.sessions);
                
                dailyChartInstance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Pengguna Aktif',
                            data: usersData,
                            borderColor: 'rgba(0, 123, 255, 1)',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            fill: true,
                            tension: 0.4
                        }, {
                            label: 'Sesi',
                            data: sessionsData,
                            borderColor: 'rgba(23, 162, 184, 1)',
                            backgroundColor: 'rgba(23, 162, 184, 0.1)',
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: { y: { beginAtZero: true } },
                        interaction: { intersect: false, mode: 'index' },
                    }
                });
            }
            
            function renderCohortTable(data) {
                const container = document.getElementById('cohort-table');
                 if (!data || data.length === 0) {
                    container.innerHTML = `<p class="loading">Tidak ada data retensi tersedia.</p>`;
                    return;
                }
                
                let tableHtml = '<table class="cohort-grid"><thead><tr><th>Cohort</th><th>Total Users</th><th>Week 0</th><th>Week 1</th><th>Week 2</th><th>Week 3</th><th>Week 4</th></tr></thead><tbody>';
                data.forEach(cohort => {
                    tableHtml += `<tr><td>${cohort.cohort}</td><td>${cohort.total_initial_users.toLocaleString()}</td>`;
                    for(let i = 0; i <= 4; i++) {
                        const weekData = cohort.retention[`Week ${i}`];
                        if (weekData) {
                            const color = getColorForPercentage(weekData.percentage);
                            tableHtml += `<td><div class="cohort-cell" style="background-color: ${color};">${weekData.percentage}%</div></td>`;
                        } else {
                            tableHtml += '<td></td>';
                        }
                    }
                    tableHtml += '</tr>';
                });
                
                tableHtml += '</tbody></table>';
                container.innerHTML = tableHtml;
            }


            // =======================
            // INITIALIZATION & EVENTS
            // =======================
            document.getElementById('period-filters').addEventListener('click', function(e) {
                if (e.target.tagName === 'BUTTON') {
                    document.querySelectorAll('#period-filters button').forEach(btn => btn.classList.remove('active'));
                    e.target.classList.add('active');
                    currentPeriod = e.target.dataset.period;
                    fetchHistoricalData(currentPeriod);
                }
            });

            // Initial data load
            fetchRealtimeData();
            fetchHistoricalData(currentPeriod);

            // Set interval for realtime data
            setInterval(fetchRealtimeData, 30000); // Refresh every 30 seconds
        });
    </script>
</body>
</html>