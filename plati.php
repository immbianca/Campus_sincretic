<?php
include "db.php";

// Setări
$TARIF_LUNI_AN = 10; // Presupunem un an academic de 10 luni (Septembrie - Iunie)
$SEMESTRU_LUNI = 5;  // Presupunem un semestru de 5 luni

// --- Funcții Utilitare pentru Calculul Restanțelor ---

/**
 * Calculează suma totală datorată, suma plătită și restanța curentă pentru un student.
 * Se bazează pe tariful lunar și pe presupunerea unui an academic de 10 luni.
 *
 * @param PDO $pdo Obiectul de conexiune PDO.
 * @param int $studentId ID-ul studentului.
 * @return array {total_datorat, total_platit, restant, tarif_lunar}
 */
function calculeazaRestantaStudent($pdo, $studentId) {
    global $TARIF_LUNI_AN;

    // 1. Obține tariful lunar curent (pe loc) și data repartizării active
    $stmt = $pdo->prepare("
        SELECT
            tc.tarif_pe_loc,
            r.data_repartizare
        FROM repartizare r
        JOIN camera c ON r.id_camera = c.id_camera
        JOIN tip_camera tc ON c.id_tip_camera = tc.id_tip_camera
        WHERE r.id_student = ? AND r.activ = 1
        ORDER BY r.data_repartizare DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $repartizare = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$repartizare) {
        return [
            'total_datorat' => 0.00,
            'total_platit' => 0.00,
            'restant' => 0.00,
            'tarif_lunar' => 0.00,
            'status' => 'Fără repartizare activă'
        ];
    }

    $tarif_lunar = (float)$repartizare['tarif_pe_loc'];
    $data_repartizare = $repartizare['data_repartizare'];

    // 2. Calculează suma totală Datorată
    // Pentru simplitate, presupunem că un student datorează tariful lunar * 10 luni pentru anul curent.
    // În aplicațiile reale, ar trebui să se calculeze prorata pe data curentă vs. data_repartizare.
    $total_datorat = $tarif_lunar * $TARIF_LUNI_AN;

    // 3. Calculează suma totală Plătită
    $stmt_plati = $pdo->prepare("
        SELECT SUM(suma_platita) AS total_platit
        FROM chitanta
        WHERE id_student = ?
    ");
    $stmt_plati->execute([$studentId]);
    $plata_totala = $stmt_plati->fetch(PDO::FETCH_ASSOC)['total_platit'] ?? 0.00;
    $total_platit = (float)$plata_totala;

    // 4. Calculează Restanța
    $restant = max(0.00, $total_datorat - $total_platit);

    return [
        'total_datorat' => $total_datorat,
        'total_platit' => $total_platit,
        'restant' => $restant,
        'tarif_lunar' => $tarif_lunar,
        'status' => 'Activ'
    ];
}


// --- Acțiuni Formular (Adăugare Plată) ---
if (isset($_POST['add_plat'])) {
    $id_student = intval($_POST['id_student'] ?? 0);
    $suma = (float)($_POST['suma_platita'] ?? 0.00);
    $perioada = trim($_POST['perioada'] ?? '');

    if ($id_student > 0 && $suma > 0 && $perioada !== '') {
        try {
            // Obține datele studentului pentru chitanță
            $stmt_stud = $pdo->prepare("SELECT nume, prenume FROM student WHERE id_student = ?");
            $stmt_stud->execute([$id_student]);
            $student = $stmt_stud->fetch(PDO::FETCH_ASSOC);
            
            // Verifică dacă există studentul și adaugă plata
            if ($student) {
                $stmt = $pdo->prepare("
                    INSERT INTO chitanta (id_student, perioada, data_emiterii, suma_platita)
                    VALUES (?, ?, CURDATE(), ?)
                ");
                $stmt->execute([$id_student, $perioada, $suma]);
                
                // Opțional: Aici poți adăuga logica de generare a chitanței PDF/HTML.
                // Pentru moment, doar redirecționăm.
            }
        } catch (PDOException $e) {
            echo "<script>alert('Eroare la înregistrarea plății: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
    header("Location: plati.php?student=" . $id_student);
    exit;
}

// --- Acțiuni Formular (Ștergere Plată) ---
if (isset($_GET['delete_chitanta'])) {
    $id_chitanta = intval($_GET['delete_chitanta']);
    $stmt = $pdo->prepare("DELETE FROM chitanta WHERE id_chitanta = ?");
    $stmt->execute([$id_chitanta]);
    // Redirecționare la același student după ștergere
    $student_id_redirect = intval($_GET['student'] ?? 0);
    header("Location: plati.php?student=" . $student_id_redirect);
    exit;
}

// --- Obținere Date pentru Afișare ---
$student_selectat_id = isset($_GET['student']) ? intval($_GET['student']) : 0;
$luni_anual = $TARIF_LUNI_AN;
$luni_semestrial = $SEMESTRU_LUNI;

// Lista de studenți pentru formularul de plată și tabele
$studenti = $pdo->query("SELECT id_student, nume, prenume FROM student ORDER BY nume, prenume")->fetchAll(PDO::FETCH_ASSOC);

// Obține lista de studenți cu detaliile de plată calculate
$studenti_cu_restante = [];
foreach ($studenti as $s) {
    $date_plata = calculeazaRestantaStudent($pdo, $s['id_student']);
    $studenti_cu_restante[] = array_merge($s, $date_plata);
}

// Detalii și Istoric Plăți pentru studentul selectat
$istoric_plati = [];
if ($student_selectat_id > 0) {
    $stmt_istoric = $pdo->prepare("
        SELECT * FROM chitanta 
        WHERE id_student = ? 
        ORDER BY data_emiterii DESC
    ");
    $stmt_istoric->execute([$student_selectat_id]);
    $istoric_plati = $stmt_istoric->fetchAll(PDO::FETCH_ASSOC);

    // Obține detalii complete student selectat
    $stmt_detalii = $pdo->prepare("
        SELECT 
            s.nume, s.prenume, s.CNP, s.anul_studiu,
            f.nume_facultate,
            c.nume_camin, ca.nr_camera
        FROM student s
        LEFT JOIN facultate f ON s.id_facultate = f.id_facultate
        LEFT JOIN repartizare r ON s.id_student = r.id_student AND r.activ = 1
        LEFT JOIN camera ca ON r.id_camera = ca.id_camera
        LEFT JOIN camin c ON ca.id_camin = c.id_camin
        WHERE s.id_student = ?
    ");
    $stmt_detalii->execute([$student_selectat_id]);
    $detalii_student = $stmt_detalii->fetch(PDO::FETCH_ASSOC);
    
    // Calculează starea financiară pentru studentul selectat
    $stare_financiara = calculeazaRestantaStudent($pdo, $student_selectat_id);
    $detalii_student = array_merge($detalii_student, $stare_financiara);
}

// --- Logica de Rapoarte ---
$raport_incasari = [];
try {
    // Raport simplu de încasări pe an
    $stmt_raport = $pdo->query("
        SELECT 
            YEAR(data_emiterii) AS an, 
            SUM(suma_platita) AS total_incasat 
        FROM chitanta 
        GROUP BY an 
        ORDER BY an DESC
    ");
    $raport_incasari = $stmt_raport->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // În cazul în care baza de date e goală
}


?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Plăți și Situație Financiară</title>
    <style>
        body { background: transparent; font-family: Arial, sans-serif; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); }
        h2, h3 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 20px; }
        .grid-container { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; margin-top: 20px; }
        .form-card, .info-card { background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #ddd; }
        
        /* Formular */
        .form-card select, .form-card input[type="number"], .form-card input[type="text"] {
            width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
        }
        .form-card button { background: #10b981; color: white; padding: 10px 15px; border: none; border-radius: 8px; cursor: pointer; transition: background 0.3s; }
        .form-card button:hover { background: #0e8f6d; }

        /* Tabele */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f0f0f0; font-weight: bold; color: #333; }
        tr:hover { background-color: #f5f5f5; }
        .btn-small { padding: 5px 10px; font-size: 12px; border-radius: 6px; }
        .btn-danger { background: #e11d48; color: white; border: none; }
        .btn-danger:hover { background: #b31038; }
        
        /* NOU: Stil pentru butonul verde (Detaliu/Istoric) */
        .btn-success { background: #10b981; color: white; border: none; }
        .btn-success:hover { background: #0e8f6d; }

        /* Stiluri pentru restanțe/plăți */
        .restant-zero { color: #10b981; font-weight: bold; }
        .restant-pozitiv { color: #e11d48; font-weight: bold; }
        .restant-status { font-weight: bold; padding: 4px 8px; border-radius: 4px; display: inline-block; }
        .status-ok { background: #d1fae5; color: #065f46; }
        .status-alert { background: #fee2e2; color: #991b1b; }

        .details-list { list-style: none; padding: 0; margin: 10px 0; }
        .details-list li { margin-bottom: 5px; padding: 5px 0; border-bottom: 1px dotted #eee; }
        .details-list li strong { display: inline-block; width: 150px; }

        /* Raporate */
        .report-table { max-width: 400px; margin-top: 15px; }
        .report-table th, .report-table td { text-align: center; }
        .raport-section { margin-top: 40px; border-top: 1px solid #ddd; padding-top: 20px; }

    </style>
</head>
<body>

<div class="container">
    <h2>Gestionarea Plăților și Situația Financiară a Studenților</h2>

    <div class="grid-container">
        <!-- Secțiunea 1: Adăugare Plată (Chitanță) -->
        <div class="form-card">
            <h3>Înregistrează Plată Nouă</h3>
            <form method="POST" action="plati.php">
                <label for="id_student">Selectează Student:</label>
                <select name="id_student" id="id_student" required>
                    <option value="">-- Alege Student --</option>
                    <?php foreach ($studenti as $s): ?>
                        <option value="<?= $s['id_student'] ?>" 
                            <?= $student_selectat_id == $s['id_student'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['nume'] . " " . $s['prenume']) ?> (ID: <?= $s['id_student'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="perioada">Perioada Plătită (ex: Semestrul I, Anual):</label>
                <input type="text" name="perioada" id="perioada" required 
                       placeholder="Ex: Semestrul I (<?= $luni_semestrial ?> luni)" value="Semestrul I" />

                <label for="suma_platita">Suma Plătită (RON):</label>
                <input type="number" name="suma_platita" id="suma_platita" required min="1" step="0.01" 
                       placeholder="Ex: 2500.00" />
                
                <button type="submit" name="add_plat">Emite Chitanță & Salvează</button>
            </form>
        </div>

        <!-- Secțiunea 2: Detalii și Istoric Plăți Student Selectat -->
        <div class="info-card">
            <?php if ($student_selectat_id > 0 && isset($detalii_student)): ?>
                <h3>Detalii Financiare: <?= htmlspecialchars($detalii_student['nume'] . " " . $detalii_student['prenume']) ?></h3>
                
                <ul class="details-list">
                    <li><strong>CNP:</strong> <?= htmlspecialchars($detalii_student['CNP'] ?? '-') ?></li>
                    <li><strong>Facultate:</strong> <?= htmlspecialchars($detalii_student['nume_facultate'] ?? '-') ?></li>
                    <li><strong>Cazare:</strong> <?= htmlspecialchars($detalii_student['nume_camin'] ?? '-') ?> / Camera: <?= htmlspecialchars($detalii_student['nr_camera'] ?? '-') ?></li>
                    <li><strong>Tarif Lunar:</strong> <?= number_format($detalii_student['tarif_lunar'], 2) ?> RON</li>
                </ul>

                <p>
                    <span class="restant-status status-ok">
                        Datorat Total Anual (<?= $luni_anual ?> luni): 
                        <?= number_format($detalii_student['total_datorat'], 2) ?> RON
                    </span>
                </p>
                <p>
                    <span class="restant-status status-ok">
                        Plătit Total: 
                        <?= number_format($detalii_student['total_platit'], 2) ?> RON
                    </span>
                </p>
                <p>
                    Restantă Curentă: 
                    <span class="restant-status <?= $detalii_student['restant'] > 0 ? 'status-alert' : 'status-ok' ?>">
                        <?= number_format($detalii_student['restant'], 2) ?> RON
                    </span>
                </p>

                <h4>Istoric Plăți (Chitanțe)</h4>
                <?php if (!empty($istoric_plati)): ?>
                    <table>
                        <tr>
                            <th>ID Chitanță</th>
                            <th>Perioadă</th>
                            <th>Suma Plătită</th>
                            <th>Data Emiterii</th>
                            <th>Acțiuni</th>
                        </tr>
                        <?php foreach ($istoric_plati as $chitanta): ?>
                            <tr>
                                <td><?= $chitanta['id_chitanta'] ?></td>
                                <td><?= htmlspecialchars($chitanta['perioada']) ?></td>
                                <td><?= number_format($chitanta['suma_platita'], 2) ?> RON</td>
                                <td><?= date('d.m.Y', strtotime($chitanta['data_emiterii'])) ?></td>
                                <td>
                                    <a href="?delete_chitanta=<?= $chitanta['id_chitanta'] ?>&student=<?= $student_selectat_id ?>" 
                                       onclick="return confirm('Sigur ștergi chitanța #<?= $chitanta['id_chitanta'] ?>? Această acțiune va afecta restanța studentului.');">
                                        <button class="btn-small btn-danger">Șterge</button>
                                    </a>
                                    <!-- Aici ar fi linkul pentru GENERARE CHITANȚĂ PDF/Vizualizare -->
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p>Nu există plăți înregistrate pentru acest student.</p>
                <?php endif; ?>
            <?php else: ?>
                <p>Selectează un student din lista de mai jos sau din formularul "Înregistrează Plată Nouă" pentru a vedea detaliile financiare și istoricul plăților.</p>
            <?php endif; ?>
        </div>
    </div>


    <!-- Secțiunea 3: Lista Studenților cu Restanțe Cumulate -->
    <h2 style="margin-top: 40px;">Lista Studenților cu Situația Financiară (Restanțe Cumulate)</h2>
    
    <table>
        <tr>
            <th>ID</th>
            <th>Nume Student</th>
            <th>Tarif Lunar</th>
            <th>Total Datorat (<?= $luni_anual ?> luni)</th>
            <th>Total Plătit</th>
            <th>Restantă</th>
            <th>Acțiuni</th>
        </tr>

        <?php if (empty($studenti_cu_restante)): ?>
            <tr><td colspan="7">Nu există studenți înregistrați.</td></tr>
        <?php else: ?>
            <?php foreach ($studenti_cu_restante as $s): ?>
                <tr>
                    <td><?= $s['id_student'] ?></td>
                    <td><?= htmlspecialchars($s['nume'] . " " . $s['prenume']) ?></td>
                    <td><?= number_format($s['tarif_lunar'], 2) ?> RON</td>
                    <td><?= number_format($s['total_datorat'], 2) ?> RON</td>
                    <td><?= number_format($s['total_platit'], 2) ?> RON</td>
                    <td class="<?= $s['restant'] > 0 ? 'restant-pozitiv' : 'restant-zero' ?>">
                        <?= number_format($s['restant'], 2) ?> RON
                    </td>
                    <td>
                        <a href="?student=<?= $s['id_student'] ?>">
                            <!-- Am schimbat clasa de la btn-primary la btn-success -->
                            <button class="btn-small btn-success">Detalii & Istoric</button> 
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>


    <!-- Secțiunea 4: Rapoarte Situații Incasări/Restanțe -->
    <div class="raport-section">
        <h2>Rapoarte Universitate</h2>

        <!-- Raport Incasări -->
        <div style="display: inline-block; width: 48%; vertical-align: top;">
            <h3>Situație cu încasările Universității (Anual)</h3>
            <?php if (!empty($raport_incasari)): ?>
                <table class="report-table">
                    <tr>
                        <th>An</th>
                        <th>Total Încăsat (RON)</th>
                    </tr>
                    <?php 
                    $total_general_incasari = 0;
                    foreach ($raport_incasari as $r): 
                        $total_general_incasari += $r['total_incasat'];
                    ?>
                        <tr>
                            <td><?= $r['an'] ?></td>
                            <td><?= number_format($r['total_incasat'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight: bold; background: #e0f2f1;">
                        <td>TOTAL GENERAL</td>
                        <td><?= number_format($total_general_incasari, 2) ?> RON</td>
                    </tr>
                </table>
            <?php else: ?>
                <p>Nu există înregistrări de plăți pentru a genera raportul de încasări.</p>
            <?php endif; ?>
        </div>

        <!-- Raport Studenți Restanțieri -->
        <div style="display: inline-block; width: 48%; vertical-align: top;">
            <h3>Lista Studenților cu Sume Rămase de Încăsat (Restanțieri)</h3>
            <?php 
            $restantieri = array_filter($studenti_cu_restante, fn($s) => $s['restant'] > 0);
            $total_restante_cumulate = array_reduce($restantieri, fn($sum, $s) => $sum + $s['restant'], 0);
            ?>
            <?php if (!empty($restantieri)): ?>
                <table class="report-table" style="max-width: 100%;">
                    <tr>
                        <th>Nume Student</th>
                        <th>Restantă Curentă (RON)</th>
                    </tr>
                    <?php foreach ($restantieri as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['nume'] . " " . $r['prenume']) ?></td>
                            <td class="restant-pozitiv"><?= number_format($r['restant'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight: bold; background: #ffebee;">
                        <td>TOTAL RESTANȚE CUMULATE</td>
                        <td><?= number_format($total_restante_cumulate, 2) ?> RON</td>
                    </tr>
                </table>
            <?php else: ?>
                <p>Felicitări! Nu există studenți cu restanțe în baza de date (pentru anul academic curent, conform calculului de 10 luni).</p>
            <?php endif; ?>
        </div>

    </div>

</div>

</body>
</html>