<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
/** @var PDO $pdo */

include "db.php"; 

function getCount($pdo, $sql) {
    try {
        $res = $pdo->query($sql);
        return $res ? $res->fetchColumn() : 0;
    } catch (Exception $e) {
        return 0;
    }
}

$total_studenti = getCount($pdo, "SELECT COUNT(*) FROM student");
$total_camine   = getCount($pdo, "SELECT COUNT(*) FROM camin");
$total_facultati = getCount($pdo, "SELECT COUNT(*) FROM facultate");
$total_locuri   = getCount($pdo, "SELECT SUM(nr_locuri_total) FROM camera") ?: 0;
$ocupate        = getCount($pdo, "SELECT COUNT(*) FROM repartizare WHERE activ = 1");

$procent = ($total_locuri > 0) ? round(($ocupate / $total_locuri) * 100) : 0;
$libere = $total_locuri - $ocupate;
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <style>
        /* DESIGN: FUNDAL ALB, ACCENTE ALBASTRU SI VERDE */
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            margin: 0;
            padding: 40px;
            background-color: #ffffff; /* FUNDAL ALB */
            color: #1e3a8a; /* TEXT ALBASTRUL INCHIS */
        }

        .header {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 20px;
            margin-bottom: 40px;
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
            color: #1e3a8a;
        }

        .header h1 span {
            color: #10b981; /* VERDE */
        }

        /* Carduri Statistici */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: #f8fafc; /* Albastru foarte deschis */
            border: 1px solid #e2e8f0;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: #10b981;
        }

        .stat-card .label {
            display: block;
            font-size: 13px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: 900;
            color: #1e3a8a;
        }

        /* Sectiunea de Ocupare */
        .occupancy-container {
            background: #1e3a8a; /* Fundal Albastru pentru contrast */
            color: #ffffff;
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(30, 58, 138, 0.2);
        }

        .occupancy-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .occupancy-header h3 {
            margin: 0;
            font-size: 20px;
        }

        .percentage-tag {
            background: #10b981; /* Verde */
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 18px;
        }

        /* Bara de progres */
        .progress-track {
            background: rgba(255, 255, 255, 0.2);
            height: 12px;
            border-radius: 6px;
            overflow: hidden;
            margin: 20px 0;
        }

        .progress-fill {
            background: #10b981;
            height: 100%;
            width: <?= $procent ?>%;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.5);
        }

        .stats-footer {
            font-size: 14px;
            opacity: 0.9;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Control Panel <span>GAD</span></h1>
        <p style="color: #64748b; margin-top: 5px;">Monitorizare Campus Universitar</p>
    </div>

    

    <div class="stats-grid">
        <div class="stat-card">
            <span class="label">Studenți Înscriși</span>
            <span class="value"><?= $total_studenti ?></span>
        </div>

        <div class="stat-card">
            <span class="label">Locuri Disponibile</span>
            <span class="value"><?= $libere ?></span>
        </div>

        <div class="stat-card">
            <span class="label">Facultăți</span>
            <span class="value"><?= $total_facultati ?></span>
        </div>
    </div>

    <div class="occupancy-container">
        <div class="occupancy-header">
            <h3>Grad de Ocupare Cămine</h3>
            <div class="percentage-tag"><?= $procent ?>%</div>
        </div>

        <div class="progress-track">
            <div class="progress-fill"></div>
        </div>

        <div class="stats-footer">
            În prezent sunt ocupate <strong><?= $ocupate ?></strong> locuri dintr-un total de <strong><?= $total_locuri ?></strong> locuri înregistrate în sistem.
        </div>
    </div>

</body>
</html>