<?php
include "db.php";

// 1. GESTIONARE ADĂUGARE CHITANȚĂ
if (isset($_POST['add_chitanta'])) {
    $id_student = intval($_POST['id_student'] ?? 0);
    $suma_platita = floatval($_POST['suma_platita'] ?? 0);
    $perioada = trim($_POST['perioada'] ?? '');
    $data_emiterii = date('Y-m-d'); // Data plății este azi

    if ($id_student > 0 && $suma_platita > 0 && $perioada !== '') {
        $stmt = $pdo->prepare("
            INSERT INTO chitanta (id_student, perioada, data_emiterii, suma_platita)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$id_student, $perioada, $data_emiterii, $suma_platita]);
        $_SESSION['message'] = "Chitanță înregistrată cu succes!";
    } else {
        $_SESSION['error'] = "Eroare: Toate câmpurile sunt obligatorii și suma trebuie să fie pozitivă.";
    }

    header("Location: plati.php");
    exit;
}

// Preluare mesaje din sesiune
session_start();
$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message'], $_SESSION['error']);


// Preluare Studenți Repartizați (pentru formularul de chitanță)
$studenti_repartizati = $pdo->query("
    SELECT 
        s.id_student, 
        s.nume, 
        s.prenume, 
        f.nume_facultate,
        tc.tarif_pe_loc 
    FROM student s
    JOIN repartizare r ON s.id_student = r.id_student AND r.activ = 1
    LEFT JOIN facultate f ON s.id_facultate = f.id_facultate
    JOIN camera c ON r.id_camera = c.id_camera
    JOIN tip_camera tc ON c.id_tip_camera = tc.id_tip_camera
    ORDER BY s.nume, s.prenume
")->fetchAll(PDO::FETCH_ASSOC);


// 2. SITUAȚIA ÎNCASĂRILOR PE PERIOADE (Semestru/An)
$incasari = [];
$incasari_sql = "
    SELECT 
        YEAR(data_emiterii) as an, 
        (MONTH(data_emiterii) DIV 6) + 1 as semestru,
        SUM(suma_platita) as total_incasat
    FROM chitanta
    GROUP BY an, semestru
    ORDER BY an DESC, semestru DESC
";
$stmt_incasari = $pdo->query($incasari_sql);
while ($row = $stmt_incasari->fetch(PDO::FETCH_ASSOC)) {
    $perioada_str = "Anul " . $row['an'] . ", Semestrul " . $row['semestru'];
    $incasari[$perioada_str] = $row['total_incasat'];
}

// 3. SITUAȚIA SUMELOR RĂMASE DE ÎNCASAT PE STUDENT
// Notă: Presupunem că un an universitar are 10 luni de cămin (Sept - Iunie) * Tarif
// Suma DATORATĂ este un simplu exemplu, logica reală ar fi mult mai complexă
// și ar trebui să țină cont de data repartizării și de perioada acoperită de plată.
// Vom folosi un calcul SIMPLIFICAT (10 luni * tarif) pentru exemplificare.

$studenti_datorii_sql = "
    SELECT 
        s.id_student,
        s.nume, 
        s.prenume,
        tc.tarif_pe_loc,
        -- Sume Plătite
        COALESCE(SUM(ch.suma_platita), 0) AS suma_platita_totala,
        -- Suma Datorată (Exemplu: 10 luni * tarif)
        tc.tarif_pe_loc * 10 AS suma_totala_datorata 
    FROM student s
    JOIN repartizare r ON s.id_student = r.id_student AND r.activ = 1
    LEFT JOIN chitanta ch ON s.id_student = ch.id_student
    JOIN camera c ON r.id_camera = c.id_camera
    JOIN tip_camera tc ON c.id_tip_camera = tc.id_tip_camera
    GROUP BY s.id_student, s.nume, s.prenume, tc.tarif_pe_loc
    HAVING (tc.tarif_pe_loc * 10) > COALESCE(SUM(ch.suma_platita), 0)
    ORDER BY nume, prenume
";

$studenti_datorii = $pdo->query($studenti_datorii_sql)->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Gestionare Plăți</title>
    <style>
        body { background: transparent; font-family: Arial, sans-serif; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); margin-bottom: 20px; }
        h2 { color: #10b981; border-bottom: 2px solid #10b981; padding-bottom: 10px; margin-bottom: 20px; }
        h3 { color: #34d399; margin-top: 20px; margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group select, .form-group input[type="text"], .form-group input[type="number"] {
            padding: 10px;
            width: 100%;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            background-color: #f9f9f9;
        }
        .btn-primary { 
            background: #10b981; 
            color: white; 
            border: none; 
            padding: 10px 20px; 
            border-radius: 8px; 
            cursor: pointer; 
            transition: background 0.3s;
        }
        .btn-primary:hover { background: #0e8f6d; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #f2f2f2; color: #333; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 15px; font-weight: bold; }
        .alert-success { background-color: #d1e7dd; color: #0f5132; border-color: #badbcc; }
        .alert-danger { background-color: #f8d7da; color: #842029; border-color: #f5c2c7; }
        .incasari-list { list-style: none; padding: 0; }
        .incasari-list li { background: #e0f2f1; padding: 10px; border-radius: 6px; margin-bottom: 10px; display: flex; justify-content: space-between; font-weight: bold; }
        .datorie-negativa { color: #e11d48; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <h2>Înregistrare Plată (Chitanță)</h2>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Formular Adăugare Chitanță -->
    <form method="POST">
        <div class="form-group">
            <label for="id_student">Student Repartizat (Tarif curent):</label>
            <select name="id_student" id="id_student" required>
                <option value="">-- Selectează Student --</option>
                <?php foreach ($studenti_repartizati as $s): ?>
                    <option value="<?= $s['id_student'] ?>">
                        <?= htmlspecialchars($s['nume'] . " " . $s['prenume'] . " (" . $s['nume_facultate'] . " | " . number_format($s['tarif_pe_loc'], 2) . " Ron/lună)") ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="suma_platita">Suma Plătită (Ron):</label>
            <input type="number" step="0.01" min="0.01" name="suma_platita" id="suma_platita" required placeholder="Ex: 500.00">
        </div>
        
        <div class="form-group">
            <label for="perioada">Perioada Acoperită:</label>
            <input type="text" name="perioada" id="perioada" required placeholder="Ex: Semestrul I 2024/2025">
        </div>
        
        <div class="form-group">
            <input type="submit" name="add_chitanta" value="Înregistrează Chitanță" class="btn-primary">
        </div>
    </form>
</div>

<!-- Secțiune Situație Încăsări -->
<div class="container">
    <h2>Situația Încăsărilor pe Perioade</h2>
    
    <?php if (empty($incasari)): ?>
        <p>Nu există încăsări înregistrate.</p>
    <?php else: ?>
        <ul class="incasari-list">
            <?php foreach ($incasari as $perioada => $suma): ?>
                <li>
                    <span><?= htmlspecialchars($perioada) ?>:</span> 
                    <span><?= number_format($suma, 2) ?> Ron</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<!-- Secțiune Sume Rămase de Încăsat (Datorii) -->
<div class="container">
    <h2>Sume Rămase de Încăsat (Datorii Studenți)</h2>
    <p>Notă: Datoria este calculată simplificat ca (10 luni x Tarif/loc) - Suma Totală Plătită.</p>
    
    <?php if (empty($studenti_datorii)): ?>
        <p>Toți studenții repartizați și-au acoperit datoria minimă (exemplu: 10 luni de cazare).</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Student</th>
                <th>Tarif Lunar (Ron)</th>
                <th>Datorat Total (10 luni)</th>
                <th>Plătit Total</th>
                <th>Rămas de Încăsat</th>
            </tr>
            <?php foreach ($studenti_datorii as $s): 
                $ramas_de_incasat = $s['suma_totala_datorata'] - $s['suma_platita_totala'];
            ?>
                <tr>
                    <td><?= htmlspecialchars($s['nume'] . " " . $s['prenume']) ?></td>
                    <td><?= number_format($s['tarif_pe_loc'], 2) ?></td>
                    <td><?= number_format($s['suma_totala_datorata'], 2) ?></td>
                    <td><?= number_format($s['suma_platita_totala'], 2) ?></td>
                    <td class="datorie-negativa"><?= number_format($ramas_de_incasat, 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

</body>
</html>