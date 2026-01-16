<?php
include "db.php";
/** @var PDO $pdo */

// 1. LOGICA DE CALCUL PERIODICĂ
$luni_calcul = isset($_GET['perioada_vizualizata']) ? intval($_GET['perioada_vizualizata']) : 10;
if (!in_array($luni_calcul, [5, 10])) {
    $luni_calcul = 10;
}
$nume_perioada = ($luni_calcul == 5) ? "Semestru (5 luni)" : "An Academic (10 luni)";

// 2. FUNCȚIE CALCUL RESTANȚĂ (Îmbunătățită)
function calculeazaRestanta(PDO $pdo, int $id_student, int $luni): array {
    // Luăm tariful lunar. Folosim INNER JOIN pentru a ne asigura că luăm doar unde există contract activ
    /** @noinspection SqlNoDataSourceInspection */
    $stmt = $pdo->prepare("
        SELECT tc.tarif_pe_loc 
        FROM repartizare r
        INNER JOIN camera c ON r.id_camera = c.id_camera
        INNER JOIN tip_camera tc ON c.id_tip_camera = tc.id_tip_camera
        WHERE r.id_student = ? AND r.activ = 1 
        LIMIT 1
    ");
    $stmt->execute([$id_student]);
    $tarif = $stmt->fetchColumn();

    // Dacă studentul NU este cazat nicăieri, datoria lui este 0
    if ($tarif === false) {
        $tarif = 0;
    }

    // Calculăm totalul plătit din tabelul chitanta
    /** @noinspection SqlNoDataSourceInspection */
    $stmt_p = $pdo->prepare("SELECT SUM(suma_platita) FROM chitanta WHERE id_student = ?");
    $stmt_p->execute([$id_student]);
    $platit = $stmt_p->fetchColumn() ?: 0;

    $datorat = (float)$tarif * $luni;
    $restant = max(0, $datorat - (float)$platit);

    return [
            'datorat' => $datorat,
            'platit'  => (float)$platit,
            'restant' => $restant,
            'tarif'   => (float)$tarif
    ];
}

// 3. SALVARE PLATĂ NOUĂ ȘI GENERARE CHITANȚĂ
$chitanta_generata = null;
if (isset($_POST['add_plat'])) {
    $id_s = intval($_POST['id_student']);
    $suma = floatval($_POST['suma_platita']);
    $per = "Plata " . $nume_perioada;

    if ($id_s > 0 && $suma > 0) {
        /** @noinspection SqlNoDataSourceInspection */
        $stmt = $pdo->prepare("INSERT INTO chitanta (id_student, perioada, data_emiterii, suma_platita) VALUES (?, ?, CURDATE(), ?)");
        $stmt->execute([$id_s, $per, $suma]);
        
        // Preluăm datele pentru chitanță
        $last_id = $pdo->lastInsertId();
        /** @noinspection SqlNoDataSourceInspection */
        $stmt_student = $pdo->prepare("SELECT nume, prenume FROM student WHERE id_student = ?");
        $stmt_student->execute([$id_s]);
        $student_info = $stmt_student->fetch(PDO::FETCH_ASSOC);
        
        $chitanta_generata = [
            'id' => $last_id,
            'nume' => $student_info['nume'] . ' ' . $student_info['prenume'],
            'suma' => $suma,
            'data' => date('d.m.Y'),
            'descriere' => $per
        ];
    }
}

// 4. PRELUARE LISTĂ STUDENȚI
/** @noinspection SqlNoDataSourceInspection */
$stmt_studenti = $pdo->query("SELECT id_student, nume, prenume FROM student ORDER BY nume ASC");
$studenti = $stmt_studenti ? $stmt_studenti->fetchAll(PDO::FETCH_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Plăți Campus</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Adăugat position: relative și z-index pentru a fi deasupra fundalului animat din styles.css */
        .container { 
            background: white; 
            padding: 20px; 
            border-radius: 12px; 
            max-width: 1000px; 
            margin: 20px auto; 
            color: #333; 
            position: relative; 
            z-index: 10; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .no-print { background: #f8fafc; padding: 20px; margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; }
        th, td { border: 1px solid #e2e8f0; padding: 12px; text-align: left; }
        th { background: #f1f5f9; font-weight: 600; }
        .restant { color: #e11d48; font-weight: bold; }
        /* noinspection CssUnusedSymbol */
        .ok { color: #10b981; font-weight: bold; }
        .btn { cursor: pointer; padding: 10px 15px; border-radius: 5px; border: none; font-weight: 600; }
        
        .chitanta-print {
            display: none;
            border: 2px solid #333;
            padding: 40px;
            margin: 20px;
            max-width: 800px;
            font-family: 'Courier New', Courier, monospace;
        }

        @media print {
            .no-print, nav, header, button, .btn, form, .situatie-financiara { display: none !important; }
            body { background: white !important; }
            .container { box-shadow: none; border: none; width: 100%; max-width: 100%; margin: 0; padding: 0; }
            
            /* Dacă avem o chitanță generată, o afișăm doar pe ea */
            <?php if ($chitanta_generata): ?>
            .chitanta-print { display: block !important; }
            <?php else: ?>
            /* Altfel afișăm tabelul */
            .situatie-financiara { display: block !important; }
            table { border: 1px solid #000; width: 100%; }
            th, td { border: 1px solid #000; color: black; }
            <?php endif; ?>
            
            /* Asigurăm că textul e negru la print */
            * { color: black !important; }
        }
    </style>
</head>
<body>

<div class="container">
    
    <?php if ($chitanta_generata): ?>
    <div class="chitanta-print">
        <h2 style="text-align: center; border-bottom: 1px dashed #000; padding-bottom: 10px;">CHITANȚĂ DE PLATĂ</h2>
        <p><strong>Nr. Chitanță:</strong> #<?= str_pad($chitanta_generata['id'], 6, '0', STR_PAD_LEFT) ?></p>
        <p><strong>Data:</strong> <?= $chitanta_generata['data'] ?></p>
        <br>
        <p><strong>Am primit de la:</strong> <?= htmlspecialchars($chitanta_generata['nume']) ?></p>
        <p><strong>Suma de:</strong> <?= number_format($chitanta_generata['suma'], 2) ?> RON</p>
        <p><strong>Reprezentând:</strong> <?= htmlspecialchars($chitanta_generata['descriere']) ?></p>
        <br><br>
        <div style="display: flex; justify-content: space-between; margin-top: 50px;">
            <div>Casier,<br>..................</div>
            <div>Semnătură plătitor,<br>..................</div>
        </div>
    </div>
    
    <div class="no-print" style="background: #dcfce7; border-color: #86efac; color: #166534;">
        <h3>✅ Plată înregistrată cu succes!</h3>
        <p>Chitanța a fost generată pentru <strong><?= htmlspecialchars($chitanta_generata['nume']) ?></strong>.</p>
        <button onclick="window.print()" class="btn" style="background: #166534; color: white; margin-top: 10px;">
            🖨️ Tipărește Chitanța Acum
        </button>
        <a href="plati.php" class="btn" style="background: #fff; border: 1px solid #166534; color: #166534; text-decoration: none; display: inline-block; margin-left: 10px;">
            Înapoi la listă
        </a>
    </div>
    <?php endif; ?>

    <div class="situatie-financiara">
        <h2 style="border-bottom: 2px solid #10b981; padding-bottom: 10px;">📊 Evidență Încasări Campus</h2>

        <div class="no-print">
            <form method="GET" style="margin-bottom: 15px;">
                <label for="perioada_vizualizata" style="font-weight:bold;">Afișează raportul pentru:</label>
                <select id="perioada_vizualizata" name="perioada_vizualizata" onchange="this.form.submit()" style="padding: 5px; border-radius: 4px; border: 1px solid #ccc;">
                    <option value="10" <?= ($luni_calcul == 10) ? 'selected' : '' ?>>An Academic (10 luni)</option>
                    <option value="5" <?= ($luni_calcul == 5) ? 'selected' : '' ?>>Semestru (5 luni)</option>
                </select>
            </form>

            <button onclick="window.print()" class="btn" style="background: #3b82f6; color: white;">
                🖨️ Tipărește Raport General PDF
            </button>
        </div>

        <div class="no-print">
            <h3>➕ Înregistrează Plată Nouă</h3>
            <form method="POST" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <label for="id_student" style="display:none;">Student</label>
                <select id="id_student" name="id_student" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="">-- Alege Student --</option>
                    <?php if (empty($studenti)): ?>
                        <option value="" disabled>Nu există studenți în baza de date</option>
                    <?php else: ?>
                        <?php foreach($studenti as $s): ?>
                            <option value="<?= $s['id_student'] ?>"><?= htmlspecialchars($s['nume'] . " " . $s['prenume']) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <label for="suma_platita" style="display:none;">Suma</label>
                <input id="suma_platita" type="number" name="suma_platita" placeholder="Suma (RON)" step="0.01" required style="padding: 8px; width: 120px; border: 1px solid #ccc; border-radius: 4px;">
                <button type="submit" name="add_plat" class="btn" style="background: #10b981; color:white;">Salvează Plata</button>
            </form>
        </div>

        <h3>Situație Financiară - Perioada: <span style="color: #10b981;"><?= $nume_perioada ?></span></h3>
        
        <?php if (empty($studenti)): ?>
            <p style="color: red; padding: 20px; text-align: center;">Nu au fost găsiți studenți în baza de date.</p>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>Nume Student</th>
                    <th>Tarif/Lună</th>
                    <th>Total Datorat</th>
                    <th>Total Achitat</th>
                    <th>Restanță</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $tot_incasat = 0;
                $tot_restante = 0;

                foreach ($studenti as $s):
                    $fin = calculeazaRestanta($pdo, (int)$s['id_student'], $luni_calcul);
                    $tot_incasat += $fin['platit'];
                    $tot_restante += $fin['restant'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($s['nume'] . " " . $s['prenume']) ?></td>
                        <td><?= number_format($fin['tarif'], 2) ?> RON</td>
                        <td><?= number_format($fin['datorat'], 2) ?> RON</td>
                        <td><?= number_format($fin['platit'], 2) ?> RON</td>
                        <td class="<?= $fin['restant'] > 0 ? 'restant' : 'ok' ?>">
                            <?= number_format($fin['restant'], 2) ?> RON
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot style="background: #f1f5f9; font-weight: bold;">
                <tr>
                    <td colspan="3" style="text-align: right;">TOTAL GENERAL CAMPUS:</td>
                    <td style="color: #10b981;"><?= number_format($tot_incasat, 2) ?> RON</td>
                    <td class="restant"><?= number_format($tot_restante, 2) ?> RON</td>
                </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>