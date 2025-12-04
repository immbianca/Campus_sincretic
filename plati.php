<?php
// Pornim sesiunea pentru a gestiona mesajele de stare (succes/eroare)
session_start();
include "db.php";

<<<<<<< Updated upstream
$error_message = $_SESSION['error'] ?? '';
$success_message = $_SESSION['message'] ?? '';
unset($_SESSION['error'], $_SESSION['message']); // Ștergem mesajele după afișare

if (isset($_POST['add_chitanta'])) {
    $id_student = intval($_POST['id_student'] ?? 0);
    $suma_platita = floatval($_POST['suma_platita'] ?? 0);
    $perioada = trim($_POST['perioada'] ?? '');
    $data_emiterii = date('Y-m-d'); 

// --- 1. GESTIONARE ADĂUGARE PLATĂ ---
if (isset($_POST['adauga_plata'])) {
    $id_student = intval($_POST['id_student'] ?? 0);
    $suma = floatval($_POST['suma_platita'] ?? 0);
    $perioada = trim($_POST['perioada'] ?? '');
    $data_emiterii = date('Y-m-d'); // Data plății

    if ($id_student > 0 && $suma > 0 && $perioada !== '') {
        try {
            // Verificăm dacă studentul este repartizat activ (obligatoriu pentru plata cazării)
            $stmt_repartizare = $pdo->prepare("
                SELECT r.id_repartizare
                FROM repartizare r
                WHERE r.id_student = ? AND r.activ = 1
            ");
            $stmt_repartizare->execute([$id_student]);
            $repartizare_existenta = $stmt_repartizare->fetch();

            if ($repartizare_existenta) {
                // Inserăm noua chitanță
                $stmt = $pdo->prepare("
                    INSERT INTO chitanta (id_student, perioada, data_emiterii, suma_platita)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$id_student, $perioada, $data_emiterii, $suma]);
                
                $_SESSION['message'] = "Plata în valoare de " . number_format($suma, 2) . " RON pentru perioada " . htmlspecialchars($perioada) . " a fost înregistrată cu succes.";
            } else {
                 $_SESSION['error'] = "Eroare: Studentul selectat nu are o repartizare activă. Nu se poate înregistra o plată de cazare.";
            }
        } catch (PDOException $e) {
             $_SESSION['error'] = "Eroare la înregistrarea plății: " . $e->getMessage();
        }
        
    } else {
        $_SESSION['error'] = "Eroare: Toate câmpurile (Student, Suma, Perioada) sunt obligatorii.";
    }

    header("Location: plati.php");
    exit;
}

session_start();
$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message'], $_SESSION['error']);


$studenti_repartizati = $pdo->query("
    SELECT 
        s.id_student, 
        s.nume, 
        s.prenume, 
        s.anul_studiu, /* Am adăugat anul de studiu pentru afișarea în select */
        cam.nume_camin,
        c.nr_camera,
        tc.tarif_pe_loc
    FROM student s
    JOIN repartizare r ON s.id_student = r.id_student AND r.activ = 1
    JOIN camera c ON r.id_camera = c.id_camera
    JOIN camin cam ON c.id_camin = cam.id_camin
    JOIN tip_camera tc ON c.id_tip_camera = tc.id_tip_camera
    ORDER BY s.nume, s.prenume
")->fetchAll(PDO::FETCH_ASSOC);

// Lista completă a chitanțelor
// Am adăugat coloana CNP pentru a avea o identificare mai clară
$chitante = $pdo->query("

$incasari = [];
$incasari_sql = "
    SELECT 
        ch.id_chitanta, 
        s.nume, 
        s.prenume,
        s.anul_studiu, 
        ch.perioada, 
        ch.data_emiterii, 
        ch.suma_platita
    FROM chitanta ch
    JOIN student s ON ch.id_student = s.id_student
    ORDER BY ch.data_emiterii DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Gestionare Plăți</title>
    <style>
        /* Stiluri de bază, preluate din styles.css, adaptate pentru containerul iframe */
        body { 
            background: transparent; 
            font-family: Arial, sans-serif; 
            padding: 20px; 
        }
        .container { 
            background: white; 
            padding: 20px; 
            border-radius: 12px; 
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1); 
            margin-bottom: 25px; 
        }
        h2 { 
            color: #059669; /* Verde închis */
            border-bottom: 3px solid #10b981; 
            padding-bottom: 10px; 
            margin-bottom: 25px; 
        }
        h3 {
             color: #374151;
             margin-top: 10px;
             margin-bottom: 20px;
        }

        /* --- Formularea îmbunătățită --- */
        .form-plata {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            padding: 20px;
            border: 1px solid #d1fae5;
            border-radius: 10px;
            background: #f0fdfa; /* Un verde foarte deschis */
        }
        
        .form-plata > div {
            flex-grow: 1;
            min-width: 200px;
        }
        
        .form-plata label {
            display: block;
            font-weight: 600;
            color: #059669;
            margin-bottom: 5px;
        }

        .form-plata select, .form-plata input[type="number"], .form-plata input[type="text"] {
            padding: 10px;
            border: 1px solid #34d399;
            border-radius: 8px;
            width: 100%;
            background-color: #ffffff;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .form-plata .btn-submit {
            padding: 10px 25px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s, transform 0.1s;
            align-self: flex-end; 
            min-width: 180px;
        }
        
        .form-plata .btn-submit:hover {
            background: #0d9467;
        }

        /* --- Mesaje --- */
        .alert { 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            font-weight: bold; 
            border: 1px solid transparent;
        }
        .alert-success { 
            background-color: #d1fae5; 
            color: #065f46; 
            border-color: #a7f3d0; 
        }
        .alert-danger { 
            background-color: #fee2e2; 
            color: #991b1b; 
            border-color: #fecaca; 
        }

        /* --- Tabel --- */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
        }
        th, td { 
            border: 1px solid #e5e7eb; 
            padding: 12px; 
            text-align: left; 
        }
        th { 
            background-color: #f3f4f6; 
            color: #374151;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        tr:nth-child(even) { 
            background-color: #f9fafb; 
        }
        
        .text-bold {
            font-weight: 700;
        }
        .text-primary {
             color: #059669;
        }
        .text-info {
             color: #3b82f6;
        }
        .tarif-loc {
            background-color: #bfdbfe; /* Albastru deschis */
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
            color: #1e40af;
            font-size: 0.85em;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Înregistrare Plată Cămin</h2>

    <!-- Mesaje de Stare -->
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- Formular de Adăugare Plată -->
    <form method="POST" class="form-plata">
        <h3>Înregistrează o nouă chitanță</h3>
        
    <form method="POST">
        <div class="form-group">
            <label for="id_student">Selectează Studentul (doar cei repartizați):</label>
            <select name="id_student" id="id_student" required>
                <option value="">-- Selectează Student --</option>
                <?php foreach ($studenti_repartizati as $s): ?>
                    <option value="<?= $s['id_student'] ?>" data-tarif="<?= $s['tarif_pe_loc'] ?>">
                        <?= htmlspecialchars($s['nume'] . " " . $s['prenume'] . 
                            " (An " . $s['anul_studiu'] . " | Loc: " . $s['nume_camin'] . " - Cam. " . $s['nr_camera'] . ")") ?>
                    </option>
                <?php endforeach; ?>
                 <?php if (empty($studenti_repartizati)): ?>
                    <option disabled>Niciun student repartizat activ.</option>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="perioada">Perioada (Ex: Noiembrie 2024):</label>
            <input type="text" name="perioada" id="perioada" placeholder="Ex: Octombrie 2025" required>
        </div>

        <div class="form-group">
            <label for="suma_platita">Suma Plătită (RON):</label>
            <input type="number" name="suma_platita" id="suma_platita" step="0.01" min="1" placeholder="Tarif Loc: 0.00 RON" required>
        </div>
        
        <input type="submit" name="adauga_plata" value="Înregistrează Plată" class="btn-submit">
    </form>
    
    <div id="tarif_info" style="margin-top: 15px; padding: 10px; border-radius: 8px; background: #e0f2f1; display: none;">
        <span class="text-bold text-primary">Tariful lunar curent este: </span>
        <span id="tarif_valoare" class="tarif-loc"></span>
    </div>
</div>

<!-- Secțiune Istoric Plăți -->
<div class="container">
    <h3>Istoric Plăți (Chitanțe)</h3>
    
    <table>
        <thead>
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

<div class="container">
    <h2>Sume Rămase de Încăsat (Datorii Studenți)</h2>
    <p>Notă: Datoria este calculată simplificat ca (10 luni x Tarif/loc) - Suma Totală Plătită.</p>
    
    <?php if (empty($studenti_datorii)): ?>
        <p>Toți studenții repartizați și-au acoperit datoria minimă (exemplu: 10 luni de cazare).</p>
    <?php else: ?>
        <table>
            <tr>
                <th>ID Chitanță</th>
                <th>Nume Student</th>
                <th>An Studiu</th> <!-- Am adăugat coloana An Studiu -->
                <th>Perioada</th>
                <th>Data Emiterii</th>
                <th>Suma Plătită (RON)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($chitante)): ?>
                <tr><td colspan="6" style="text-align: center;">Nu există plăți înregistrate în istoric.</td></tr>
            <?php else: ?>
                <?php foreach ($chitante as $ch): ?>
                    <tr>
                        <td class="text-info"><?= $ch['id_chitanta'] ?></td>
                        <!-- Am afișat numele complet al studentului -->
                        <td><?= htmlspecialchars($ch['nume'] . " " . $ch['prenume']) ?></td>
                        <td>Anul <?= $ch['anul_studiu'] ?></td>
                        <td><?= htmlspecialchars($ch['perioada']) ?></td>
                        <td><?= date('d.m.Y', strtotime($ch['data_emiterii'])) ?></td>
                        <td class="text-bold text-primary"><?= number_format($ch['suma_platita'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const studentSelect = document.getElementById('id_student');
        const sumaInput = document.getElementById('suma_platita');
        const tarifInfoDiv = document.getElementById('tarif_info');
        const tarifValoareSpan = document.getElementById('tarif_valoare');
        const perioadaInput = document.getElementById('perioada');

        // Setează perioada implicită la luna curentă (Ex: Decembrie 2025)
        function setPerioadaDefault() {
            const date = new Date();
            const monthNames = [
                "Ianuarie", "Februarie", "Martie", 
                "Aprilie", "Mai", "Iunie", 
                "Iulie", "August", "Septembrie", 
                "Octombrie", "Noiembrie", "Decembrie"
            ];
            const currentMonth = monthNames[date.getMonth()];
            const currentYear = date.getFullYear();
            perioadaInput.value = `${currentMonth} ${currentYear}`;
        }
        
        setPerioadaDefault();


        // Funcția de actualizare a tarifului și a placeholder-ului
        function updateTarifInfo() {
            const selectedOption = studentSelect.options[studentSelect.selectedIndex];
            const tarif = selectedOption.getAttribute('data-tarif');

            if (tarif) {
                // Afișează informațiile despre tarif
                tarifValoareSpan.textContent = parseFloat(tarif).toFixed(2) + " RON";
                tarifInfoDiv.style.display = 'block';

                // Setează placeholder-ul inputului de sumă
                sumaInput.placeholder = `Sugerat: ${parseFloat(tarif).toFixed(2)} RON`;
            } else {
                // Ascunde informațiile dacă nu este selectat niciun student repartizat
                tarifInfoDiv.style.display = 'none';
                sumaInput.placeholder = `Tarif Loc: 0.00 RON`;
            }
        }

        // Inițializare la încărcarea paginii
        updateTarifInfo();

        // Ascultă schimbările pe dropdown-ul de studenți
        studentSelect.addEventListener('change', updateTarifInfo);
    });
</script>

</body>
</html>