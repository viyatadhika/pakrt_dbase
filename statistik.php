<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include 'config.php';
$activePage = basename($_SERVER['PHP_SELF']);

$title = "Statistik";
include 'header.php';

/* ===================== SUMMARY DATA ===================== */
$total        = $conn->query("SELECT COUNT(*) AS jml FROM checklist_forms")->fetch_assoc()['jml'];
$totalPetugas = $conn->query("SELECT COUNT(DISTINCT nama_petugas) AS jml FROM checklist_forms")->fetch_assoc()['jml'];
$totalForm    = $conn->query("SELECT COUNT(DISTINCT form_type) AS jml FROM checklist_forms")->fetch_assoc()['jml'];
$totalArea    = $conn->query("SELECT COUNT(DISTINCT area_kerja) AS jml FROM checklist_forms WHERE area_kerja <> ''")->fetch_assoc()['jml'];

/* ===================== GRAFIK FORM ===================== */
$q1 = $conn->query("
    SELECT form_type, COUNT(*) AS total
    FROM checklist_forms
    GROUP BY form_type
    ORDER BY total DESC
");

$chartLabels = [];
$chartValues = [];

while ($row = $q1->fetch_assoc()) {
    $chartLabels[] = $row['form_type'];
    $chartValues[] = $row['total'];
}

/* ===================== GRAFIK AREA ===================== */
$q2 = $conn->query("
    SELECT area_kerja, COUNT(*) AS total
    FROM checklist_forms
    WHERE area_kerja <> ''
    GROUP BY area_kerja
    ORDER BY total DESC
");

$areaLabels = [];
$areaValues = [];

while ($row = $q2->fetch_assoc()) {
    $areaLabels[] = $row['area_kerja'];
    $areaValues[] = $row['total'];
}

/* ===================== GENERATE RANDOM COLOR ARRAY ===================== */
function generateColors($count)
{
    $colors = [];
    for ($i = 0; $i < $count; $i++) {
        $colors[] = "hsl(" . rand(1, 360) . ",70%,65%)";
    }
    return $colors;
}

$formColors = generateColors(count($chartLabels));
$areaColors = generateColors(count($areaLabels));

?>

<!-- HEADER -->
<div class="p-6 text-left">
    <h2 class="text-xl font-bold text-sky-700">Statistik Checklist</h2>
    <p class="text-sm text-gray-500 mt-1">Rekap data pekerjaan secara menyeluruh</p>
</div>

<!-- SUMMARY CARDS -->
<div class="grid grid-cols-2 gap-4 px-4 mb-6">

    <div class="card">
        <div class="icon bg-blue-500"><i class="fa-solid fa-list-check"></i></div>
        <p>Total Checklist</p>
        <h3><?= $total ?></h3>
    </div>

    <div class="card">
        <div class="icon bg-emerald-500"><i class="fa-solid fa-users"></i></div>
        <p>Total Petugas</p>
        <h3><?= $totalPetugas ?></h3>
    </div>

    <div class="card">
        <div class="icon bg-indigo-500"><i class="fa-solid fa-file-lines"></i></div>
        <p>Jenis Form</p>
        <h3><?= $totalForm ?></h3>
    </div>

    <div class="card">
        <div class="icon bg-rose-500"><i class="fa-solid fa-location-dot"></i></div>
        <p>Area Kerja</p>
        <h3><?= $totalArea ?></h3>
    </div>

</div>

<!-- PIE CHART FORM -->
<div class="chart-box">
    <h4>Proporsi Jenis Form Checklist</h4>
    <div class="chart-container">
        <canvas id="chartForm"></canvas>
    </div>
</div>

<!-- PIE CHART AREA -->
<div class="chart-box">
    <h4>Proporsi Area Kerja</h4>
    <div class="chart-container">
        <canvas id="chartArea"></canvas>
    </div>
</div>

<!-- CHART.JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(function() {
            renderCharts();
        }, 150);
    });

    function renderCharts() {

        /* PIE 1 */
        new Chart(document.getElementById("chartForm"), {
            type: "pie",
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    data: <?= json_encode($chartValues) ?>,
                    backgroundColor: <?= json_encode($formColors) ?>,
                    borderColor: "#fff",
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "bottom"
                    }
                }
            }
        });

        /* PIE 2 */
        new Chart(document.getElementById("chartArea"), {
            type: "pie",
            data: {
                labels: <?= json_encode($areaLabels) ?>,
                datasets: [{
                    data: <?= json_encode($areaValues) ?>,
                    backgroundColor: <?= json_encode($areaColors) ?>,
                    borderColor: "#fff",
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "bottom"
                    }
                }
            }
        });

    }
</script>

<!-- FINAL CSS -->
<style>
    .card {
        background: white;
        padding: 18px;
        border-radius: 18px;
        text-align: center;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 10px rgba(0, 0, 0, .06);
    }

    .card .icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        color: white;
        margin: auto;
        font-size: 18px;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .chart-box {
        background: white;
        border-radius: 18px;
        padding: 18px;
        margin: 0 16px 26px;
        border: 1px solid #e2e8f0;
        text-align: center;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .06);
    }

    .chart-box h4 {
        font-size: 14px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 10px;
    }

    /* FIX PIE CHART MOBILE */
    .chart-container {
        width: 100%;
        height: 320px !important;
        min-height: 320px !important;
        position: relative;
    }
</style>

<?php include 'nav_monitoring.php'; ?>
<?php include 'footer.php'; ?>