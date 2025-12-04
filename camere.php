<?php
// Pornim sesiunea pentru a gestiona mesajele de stare (succes/eroare)
session_start();
include "db.php";

$error_message = $_SESSION['error'] ?? '';
$success_message = $_SESSION['message'] ?? '';
unset($_SESSION['error'], $_SESSION['message']); 

$id_camin = intval($_GET['camin'] ?? 0);

if ($id_camin === 0) {
    $_SESSION['error'] = "Eroare: Nu a fost specificat un cămin valid.";
    header("Location: camine.php");
    exit;
}

// Preluare nume cămin
$stmt_camin = $pdo->prepare("SELECT nume_camin FROM camin WHERE id_camin = ?");
$stmt_camin->execute([$id_camin]);
$camin_info = $stmt_camin->fetch(PDO::FETCH_ASSOC);

if (!$camin_info) {
    $_SESSION['error'] = "Eroare: Căminul specificat nu există.";
    header("Location: camine.php");
    exit;
}
$nume_camin = htmlspecialchars($camin_info['nume_camin']);


// --- 1. GESTIONARE ADĂUGARE CAMERĂ ---
if (isset($_POST['add_camera'])) {
    $nr_camera = trim($_POST['nr_camera'] ?? '');
    $id_tip_camera = intval($_POST['id_tip_camera'] ?? 0);
    
    // Preluăm nr. de locuri din tipul de cameră
    $stmt_tip = $pdo->prepare("SELECT nr_paturi FROM tip_camera WHERE id_tip_camera = ?");
    $stmt_tip->execute([$id_tip_camera]);
    $nr_locuri_total = $stmt_tip->fetchColumn();

    if ($nr_camera !== '' && $id_tip_camera > 0 && $nr_locuri_total > 0) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO camera (id_camin, id_tip_camera, nr_camera, nr_locuri_total)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$id_camin, $id_tip_camera, $nr_camera, $nr_locuri_total]);
            $_SESSION['message'] = "Camera $nr_camera a fost adăugată cu succes.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Eroare la adăugarea camerei: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Toate câmpurile sunt obligatorii sau Tipul de Cameră nu este valid.";
    }

    header("Location: camere.php?camin=$id_camin");
    exit;
}

// --- 2. GESTIONARE ȘTERGERE CAMERĂ ---
if (isset($_GET['delete'])) {
    $id_camera = intval($_GET['delete']);
    try {
        // Se va șterge automat și repartizarea, datorită ON DELETE CASCADE
        $stmt = $pdo->prepare("DELETE FROM camera WHERE id_camera = ?");
        $stmt->execute([$id_camera]);
        $_SESSION['message'] = "Camera a fost ștearsă cu succes. Eventualele repartizări asociate au fost anulate.";
    } catch (PDOException $e) {
         $_SESSION['error'] = "Eroare la ștergere: " . $e->getMessage();
    }
    header("Location: camere.php?camin=$id_camin");
    exit;
}

// Preluare lista tipuri de camere (pentru formular)
$tipuri_camera = $pdo->query("SELECT * FROM tip_camera ORDER BY nr_paturi DESC")->fetchAll(PDO::FETCH_ASSOC);

// Preluare lista camere pentru căminul curent
$camere = $pdo->prepare("
    SELECT 
        c.id_camera, 
        c.nr_camera, 
        c.nr_locuri_total,
        tc.nr_paturi,
        tc.tarif_pe_loc,
        (SELECT COUNT(*) FROM repartizare r WHERE r.id_camera = c.id_camera AND r.activ = 1) AS locuri_ocupate
    FROM camera c
    JOIN tip_camera tc ON c.id_tip_camera = tc.id_tip_camera
    WHERE c.id_camin = ?
    ORDER BY c.nr_camera
");
$camere->execute([$id_camin]);
$lista_camere = $camere->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Camere: <?= $nume_camin ?></title>
    <style>
        body { background: transparent; font-family: Arial, sans-serif; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1); margin-bottom: 25px; }
        h2 { color: #059669; border-bottom: 3px solid #10b981; padding-bottom: 10px; margin-bottom: 25px; }
        
        .camin-header { font-size: 1.5rem; color: #374151; margin-bottom: 20px; }

        /* Formularea */
        .form-add { display: flex; gap: 15px; flex-wrap: wrap; padding: 20px; border: 1px solid #d1fae5; border-radius: 10px; background: #f0fdfa; }
        .form-group { flex-grow: 1; min-width: 250px; }
        .form-group label { display: block; font-weight: 600; color: #059669; margin-bottom: 5px; }
        
        .form-add input, .form-add select {
            padding: 10px;
            border: 1px solid #34d399;
            border-radius: 8px;
            width: 100%;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .form-add .btn-submit {
            padding: 10px 25px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
            align-self: flex-end; 
            min-width: 150px;
        }
        .form-add .btn-submit:hover { background: #0d9467; }

        /* Mesaje de Stare */
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; border: 1px solid transparent; }
        .alert-success { background-color: #d1fae5; color: #065f46; border-color: #a7f3d0; }
        .alert-danger { background-color: #fee2e2; color: #991b1b; border-color: #fecaca; }

        /* Tabel */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #e5e7eb; padding: 12px; text-align: left; }
        th { background-color: #f3f4f6; color: #374151; font-size: 0.9rem; text-transform: uppercase; }
        tr:nth-child(even) { background-color: #f9fafb; }

        .btn-danger { background: #ef4444; color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; transition: background 0.3s; }
        .btn-danger:hover { background: #dc2626; }
        .badge { padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 0.85em; }
        .badge-info { background-color: #bfdbfe; color: #1e40af; } /* Locuri */
        .badge-success { background-color: #d1fae5; color: #065f46; } /* Tarif */
    </style>
</head>
<body>

<div class="container">
    <a href="camine.php" style="float: right; text-decoration: none;"><button style="background: #3b82f6; color: white; padding: 8px 15px; border: none; border-radius: 6px; cursor: pointer;">&larr; Înapoi la Cămine</button></a>
    <h2>Gestionare Camere</h2>
    <h3 class="camin-header">Cămin: <?= $nume_camin ?></h3>

    <!-- Mesaje de Stare -->
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- Formular adăugare Cameră -->
    <form method="POST" class="form-add">
        <div class="form-group">
            <label for="nr_camera">Numărul Camerei:</label>
            <input type="text" name="nr_camera" id="nr_camera" placeholder="Ex: 101A sau 210" required>
        </div>
        <div class="form-group">
            <label for="id_tip_camera">Tip Cameră (Locuri/Tarif):</label>
            <select name="id_tip_camera" id="id_tip_camera" required>
                <option value="">-- Selectează Tipul --</option>
                <?php foreach ($tipuri_camera as $t): ?>
                    <option value="<?= $t['id_tip_camera'] ?>">
                        <?= $t['nr_paturi'] ?> locuri (<?= number_format($t['tarif_pe_loc'], 2) ?> RON/loc)
                    </option>
                <?php endforeach; ?>
                <?php if (empty($tipuri_camera)): ?>
                    <option disabled>Te rog adaugă tipuri de camere în modulul "Tipuri Cameră".</option>
                <?php endif; ?>
            </select>
        </div>
        <input type="submit" name="add_camera" value="Adaugă Cameră" class="btn-submit">
    </form>

    <!-- Tabel Camere -->
    <table>
        <thead>
            <tr>
                <th>ID Cameră</th>
                <th>Nr. Cameră</th>
                <th>Tip Locuri</th>
                <th>Tarif/Loc</th>
                <th>Status Locuri</th>
                <th>Acțiuni</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($lista_camere)): ?>
                <tr><td colspan="6" style="text-align: center;">Nu există camere definite pentru acest cămin.</td></tr>
            <?php else: ?>
                <?php foreach ($lista_camere as $c): ?>
                    <?php 
                        $locuri_libere = $c['nr_locuri_total'] - $c['locuri_ocupate'];
                        $status_badge = $locuri_libere > 0 ? "badge-info" : "btn-danger";
                    ?>
                    <tr>
                        <td><?= $c['id_camera'] ?></td>
                        <td><strong><?= htmlspecialchars($c['nr_camera']) ?></strong></td>
                        <td><?= $c['nr_locuri_total'] ?> locuri (Tip: <?= $c['nr_paturi'] ?>)</td>
                        <td><span class="badge badge-success"><?= number_format($c['tarif_pe_loc'], 2) ?> RON</span></td>
                        <td>
                            <span class="badge <?= $status_badge ?>">
                                Ocupate: <?= $c['locuri_ocupate'] ?> / Total: <?= $c['nr_locuri_total'] ?> (Libere: <?= $locuri_libere ?>)
                            </span>
                        </td>
                        <td>
                            <a href="?camin=<?= $id_camin ?>&delete=<?= $c['id_camera'] ?>"
                               onclick="return confirm('Sigur ștergi camera <?= $c['nr_camera'] ?>? Toate repartizările din ea vor fi anulate!');">
                                <button type="button" class="btn-danger">Șterge</button>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>