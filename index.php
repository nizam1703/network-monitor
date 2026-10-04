<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Network Monitoring Dashboard</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #07111f;
    color: white;
}

.container {
    max-width: 1500px;
    margin: auto;
    padding: 30px;
}

/* HEADER */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.header h1 {
    margin: 0;
    font-size: 30px;
}

.subtitle,
.last-update {
    color: #94a3b8;
}

.subtitle {
    margin-top: 7px;
}

.last-update {
    font-size: 13px;
}

/* SUMMARY */
.summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.summary-box,
.card,
.chart-card {
    background: #101b2c;
    border: 1px solid #1e293b;
}

.summary-box {
    border-radius: 12px;
    padding: 18px;
}

.summary-label {
    color: #64748b;
    font-size: 12px;
}

.summary-value {
    font-size: 28px;
    font-weight: bold;
    margin-top: 5px;
}

/* HOST CARD */
.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.card {
    border-radius: 14px;
    padding: 18px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.site-name {
    font-size: 18px;
    font-weight: bold;
}

.hostname {
    color: #64748b;
    font-size: 12px;
    margin-top: 4px;
}

/* STATUS */
.status {
    border-radius: 20px;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: bold;
}

.status-up {
    color: #22c55e;
    background: rgba(34, 197, 94, .12);
}

.status-down {
    color: #ef4444;
    background: rgba(239, 68, 68, .12);
}

/* STATISTIK */
.stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-top: 18px;
}

.stat {
    background: #0b1524;
    padding: 11px;
    border-radius: 9px;
}

.stat-label {
    color: #64748b;
    font-size: 10px;
    text-transform: uppercase;
}

.stat-value {
    font-size: 17px;
    font-weight: bold;
    margin-top: 4px;
}

/* CHART */
.chart-card {
    border-radius: 15px;
    padding: 20px;
}

.chart-title {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 15px;
}

.chart-container {
    height: 430px;
}

/* RESPONSIVE */
@media (max-width: 800px) {
    .summary {
        grid-template-columns: repeat(2, 1fr);
    }

    .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}
</style>
</head>

<body>

<div class="container">

    <!-- HEADER -->
    <div class="header">
        <div>
            <h1>Network Monitoring Dashboard</h1>
            <div class="subtitle">Realtime ICMP Monitoring</div>
        </div>

        <div id="lastUpdate" class="last-update">
            Connecting...
        </div>
    </div>

    <!-- SUMMARY -->
    <div class="summary">

        <div class="summary-box">
            <div class="summary-label">TOTAL HOST</div>
            <div id="totalHost" class="summary-value">0</div>
        </div>

        <div class="summary-box">
            <div class="summary-label">ONLINE</div>
            <div id="onlineHost"
                 class="summary-value"
                 style="color:#22c55e">0</div>
        </div>

        <div class="summary-box">
            <div class="summary-label">DOWN</div>
            <div id="downHost"
                 class="summary-value"
                 style="color:#ef4444">0</div>
        </div>

        <div class="summary-box">
            <div class="summary-label">UPDATE INTERVAL</div>
            <div class="summary-value">1s</div>
        </div>

    </div>

    <!-- HOST -->
    <div id="cards" class="cards"></div>

    <!-- CHART -->
    <div class="chart-card">
        <div class="chart-title">
            Network Latency Realtime
        </div>

        <div class="chart-container">
            <canvas id="networkChart"></canvas>
        </div>
    </div>

</div>


<script>

// ==============================
// KONFIGURASI
// ==============================

const MAX_HISTORY = 60;

const colors = [
    "#22c55e",
    "#3b82f6",
    "#f59e0b",
    "#ef4444",
    "#a855f7",
    "#06b6d4",
    "#ec4899",
    "#eab308"
];

let initialized = false;
let networkChart;

const historyData  = {};
const timeLabels   = {};
const totalRequest = {};
const failedRequest = {};


// ==============================
// MEMBUAT ID AMAN
// ==============================

function safeId(name) {
    return name.replace(/[^a-zA-Z0-9]/g, "_");
}


// ==============================
// MEMBUAT HOST CARD
// ==============================

function createCard(name, host) {

    const id = safeId(name);

    return `
        <div class="card">

            <div class="card-header">
                <div>
                    <div class="site-name">${name}</div>
                    <div class="hostname">${host}</div>
                </div>

                <div id="status-${id}" class="status">
                    Checking...
                </div>
            </div>

            <div class="stats">

                <div class="stat">
                    <div class="stat-label">Current Ping</div>
                    <div id="ping-${id}" class="stat-value">-</div>
                </div>

                <div class="stat">
                    <div class="stat-label">Average</div>
                    <div id="avg-${id}" class="stat-value">-</div>
                </div>

                <div class="stat">
                    <div class="stat-label">Minimum</div>
                    <div id="min-${id}" class="stat-value">-</div>
                </div>

                <div class="stat">
                    <div class="stat-label">Maximum</div>
                    <div id="max-${id}" class="stat-value">-</div>
                </div>

                <div class="stat">
                    <div class="stat-label">Packet Loss</div>
                    <div id="loss-${id}" class="stat-value">-</div>
                </div>

            </div>
        </div>
    `;
}


// ==============================
// MEMBUAT CHART
// ==============================

function initNetworkChart(hostNames) {

    const datasets = hostNames.map((name, index) => ({
        label: name,
        data: [],
        borderColor: colors[index % colors.length],
        backgroundColor: colors[index % colors.length],
        borderWidth: 2,
        tension: 0.25,
        pointRadius: 2,
        pointHoverRadius: 5,
        spanGaps: false
    }));

    networkChart = new Chart(
        document.getElementById("networkChart"),
        {
            type: "line",

            data: {
                labels: [],
                datasets
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,

                interaction: {
                    mode: "index",
                    intersect: false
                },

                plugins: {
                    legend: {
                        position: "top",
                        labels: {
                            color: "#ffffff",
                            usePointStyle: true
                        }
                    },

                    tooltip: {
                        callbacks: {
                            label(context) {
                                return context.raw === null
                                    ? context.dataset.label + ": DOWN"
                                    : context.dataset.label +
                                      ": " + context.raw + " ms";
                            }
                        }
                    }
                },

                scales: {
                    x: {
                        ticks: {
                            color: "#64748b",
                            maxTicksLimit: 15
                        },
                        grid: {
                            color: "#1e293b"
                        },
                        title: {
                            display: true,
                            text: "Time",
                            color: "#94a3b8"
                        }
                    },

                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: "#64748b"
                        },
                        grid: {
                            color: "#1e293b"
                        },
                        title: {
                            display: true,
                            text: "Latency (ms)",
                            color: "#94a3b8"
                        }
                    }
                }
            }
        }
    );
}


// ==============================
// HITUNG STATISTIK
// ==============================

function calculateStats(name) {

    const valid = historyData[name]
        .filter(value => value !== null);

    if (!valid.length) {
        return {
            average: null,
            min: null,
            max: null
        };
    }

    const total = valid.reduce((a, b) => a + b, 0);

    return {
        average: (total / valid.length).toFixed(1),
        min: Math.min(...valid),
        max: Math.max(...valid)
    };
}


// ==============================
// UPDATE HOST CARD
// ==============================

function updateHost(name, data) {

    const id = safeId(name);

    totalRequest[name]++;

    // Simpan history
    if (data.status === "UP") {
        historyData[name].push(data.latency);
    } else {
        historyData[name].push(null);
        failedRequest[name]++;
    }

    // Maksimal 60 data
    if (historyData[name].length > MAX_HISTORY) {
        historyData[name].shift();
    }


    // Update status
    const status = document.getElementById("status-" + id);

    if (data.status === "UP") {
        status.innerText = "● UP";
        status.className = "status status-up";
    } else {
        status.innerText = "● DOWN";
        status.className = "status status-down";
    }


    // Current ping
    document.getElementById("ping-" + id).innerText =
        data.latency !== null
            ? data.latency + " ms"
            : "Timeout";


    // Statistik
    const stats = calculateStats(name);

    document.getElementById("avg-" + id).innerText =
        stats.average !== null
            ? stats.average + " ms"
            : "-";

    document.getElementById("min-" + id).innerText =
        stats.min !== null
            ? stats.min + " ms"
            : "-";

    document.getElementById("max-" + id).innerText =
        stats.max !== null
            ? stats.max + " ms"
            : "-";


    // Packet loss
    const loss =
        (failedRequest[name] / totalRequest[name]) * 100;

    document.getElementById("loss-" + id).innerText =
        loss.toFixed(1) + "%";
}


// ==============================
// UPDATE CHART
// ==============================

function updateNetworkChart() {

    if (!networkChart) return;

    const hostNames = Object.keys(historyData);

    if (!hostNames.length) return;

    networkChart.data.labels =
        timeLabels[hostNames[0]];

    networkChart.data.datasets.forEach(dataset => {
        dataset.data = historyData[dataset.label];
    });

    networkChart.update();
}


// ==============================
// AMBIL DATA DARI ping.php
// ==============================

async function loadNetworkStatus() {

    try {

        const response = await fetch(
            "ping.php?t=" + Date.now()
        );

        const data = await response.json();
        const hostNames = Object.keys(data);


        // ----------------------
        // INISIALISASI PERTAMA
        // ----------------------
        if (!initialized) {

            const cards =
                document.getElementById("cards");

            hostNames.forEach(name => {

                cards.innerHTML += createCard(
                    name,
                    data[name].host
                );

                historyData[name] = [];
                timeLabels[name] = [];
                totalRequest[name] = 0;
                failedRequest[name] = 0;
            });

            initNetworkChart(hostNames);

            initialized = true;
        }


        // ----------------------
        // UPDATE DATA
        // ----------------------

        const time =
            new Date().toLocaleTimeString("id-ID");

        let online = 0;
        let down = 0;


        hostNames.forEach(name => {

            // Simpan waktu
            timeLabels[name].push(time);

            if (timeLabels[name].length > MAX_HISTORY) {
                timeLabels[name].shift();
            }

            // Update card
            updateHost(name, data[name]);

            // Hitung status
            if (data[name].status === "UP") {
                online++;
            } else {
                down++;
            }
        });


        // ----------------------
        // UPDATE SUMMARY
        // ----------------------

        document.getElementById("totalHost").innerText =
            hostNames.length;

        document.getElementById("onlineHost").innerText =
            online;

        document.getElementById("downHost").innerText =
            down;

        document.getElementById("lastUpdate").innerText =
            "Last Update: " + time;


        // Update grafik
        updateNetworkChart();

    }

    catch (error) {

        console.error("Monitoring error:", error);

        document.getElementById("lastUpdate").innerText =
            "Connection Error";
    }
}


// ==============================
// REALTIME MONITORING
// ==============================

async function startMonitoring() {

    while (true) {

        await loadNetworkStatus();

        await new Promise(resolve =>
            setTimeout(resolve, 1000)
        );
    }
}

startMonitoring();

</script>

</body>
</html>