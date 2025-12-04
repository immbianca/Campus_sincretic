<?php
session_start();
include "db.php";

$error_message = $_SESSION['error'] ?? '';
$success_message = $_SESSION['message'] ?? '';
unset($_SESSION['error'], $_SESSION['message']);


// ---- ADAUGARE PLATA ----
if (isset($_POST['adauga_plata'])) {
    $id_student = intval($_POST['id_student'] ?? 0);
    $suma = floatval($_POST['suma_platita'] ?? 0);
    $perioada = trim($_POST['perioada'] ?? '');
    $data_emiterii = date('Y-m-d');

    if ($id_student > 0 && $suma > 0 && $perioada !== '') {

        // verificăm dacă studentul are repartizare activă
        $stmt_rep = $pdo->prepare("
            SELECT id_repartizare 
            FROM repartizare 
            WHERE id_student = ? AND activ = 1
        ");
        $stmt_rep->execute([$id_student]);
        $are_repartizare = $stmt_rep->fetch();

        if ($are_repartizare) {
            // inserăm chitanța
            $stmt = $pdo->prepare("
                INSERT INTO chitanta (id_student, perioada, data_emiterii, suma_platita)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$id_student, $perioada, $data_emiterii, $suma]);

            $_SESSION['message'] = "Plata de " . number_format($suma, 2) . " RON pentru perioada $perioada a fost înregistrată.";
        } else {
            $_SESSION['error'] = "Studentul NU are repartizare activă. Nu se poate înregistra plata.";
        }

    } else {
        $_SESSION['error'] = "Toate câmpurile sunt obligatorii!";
    }

    header("Location: plati.php");
    exit;
}



// ---- STUDENȚI CU REPARTIZARE ACTIVĂ ----
$studenti_repartizati = $pdo->query("
    SELECT 
        s.id_student,
        s.nume,
        s.prenume,
        s.anul_studiu,
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


// ---- ISTORIC PLĂȚI ----
$chitante = $pdo->query("
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
<title>Plăți Cămin</title>

<style>
body { background: transparent; font-family: Arial; padding: 20px; }
.container { background: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; }

h2 { color: #059669; border-bottom: 3px solid #10b981; padding-bottom: 10px; }

.form-plata { display: flex; gap: 20px; flex-wrap: wrap; padding: 15px; background: #f0fdfa; border-radius: 10px; }

.form-plata select, input { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #34d399; }

.btn-submit {
    background: #10b981; border: none; padding: 10px 20px;
    color: white; border-radius: 8px; cursor: pointer; font-weight: bold;
}
.btn-submit:hover { background: #0d9467; }

table { width: 100%; border-collapse: collapse; margin-top: 15px; }
th { background: #10b981; color: white; padding: 10px; }
td { padding: 10px; border-bottom: 1px solid #ddd; }

.alert-success { background: #d1fae5; color: #065f46; padding: 10px; border-radius: 5px; }
.alert-danger { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 5px; }

</style>
</head>

<body>

<div class="container">
    <h2>Înregistrare Plată</h2>

    <?php if ($success_message): ?>
        <div class="alert-success"><?= $success_message ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert-danger"><?= $error_message ?></div>
    <?php endif; ?>

    <form method="POST" class="form-plata">
        <div>
            <label>Student</label>
            <select name="id_student" required>
                <option value="">Alege student</option>
                <?php foreach ($studenti_repartizati as $s): ?>
                    <option value="<?= $s['id_student'] ?>" data-tarif="<?= $s['tarif_pe_loc'] ?>">
                        <?= $s['nume'] . " " . $s['prenume'] ?> —
                        <?= $s['nume_camin'] ?>, Cam. <?= $s['nr_camera'] ?> —
                        Tarif: <?= $s['tarif_pe_loc'] ?> lei
                    </option>
                <?php endforeach; ?>
                <?php if (empty($studenti_repartizati)): ?>
                    <option disabled>Niciun student repartizat.</option>
                <?php endif; ?>
            </select>
        </div>

        <div>
            <label>Perioada</label>
            <input type="text" name="perioada" placeholder="Ex: Noiembrie 2024" required>
        </div>

        <div>
            <label>Suma plătită</label>
            <input type="number" name="suma_platita" step="0.01" min="1" required>
        </div>

        <input type="submit" name="adauga_plata" value="Înregistrează plata" class="btn-submit">
    </form>

</div>


<div class="container">
    <h2>Istoric Plăți</h2>

    <table>
        <tr>
            <th>ID</th>
            <th>Student</th>
            <th>An</th>
            <th>Perioadă</th>
            <th>Data</th>
            <th>Suma</th>
        </tr>

        <?php foreach ($chitante as $ch): ?>
        <tr>
            <td><?= $ch['id_chitanta'] ?></td>
            <td><?= $ch['nume'] . " " . $ch['prenume'] ?></td>
            <td><?= $ch['anul_studiu'] ?></td>
            <td><?= $ch['perioada'] ?></td>
            <td><?= date('d.m.Y', strtotime($ch['data_emiterii'])) ?></td>
            <td><?= number_format($ch['suma_platita'], 2) ?> RON</td>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($chitante)): ?>
            <tr><td colspan="6" style="text-align:center;">Nu există plăți înregistrate.</td></tr>
        <?php endif; ?>

    </table>
</div>

</body>
</html>
