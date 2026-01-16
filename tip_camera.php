<?php
// Pornim sesiunea pentru a gestiona mesajele de stare (succes/eroare)
session_start();
include "db.php";
/** @var PDO $pdo */

$error_message = $_SESSION['error'] ?? '';
$success_message = $_SESSION['message'] ?? '';
unset($_SESSION['error'], $_SESSION['message']); 

// --- 1. GESTIONARE ADĂUGARE TIP CAMERĂ ---
if (isset($_POST['add'])) {
    $nr_paturi = intval($_POST['nr_paturi'] ?? 0);
    $tarif = floatval($_POST['tarif'] ?? 0);

    if ($nr_paturi > 0 && $tarif > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO tip_camera (nr_paturi, tarif_pe_loc) VALUES (?, ?)");
            $stmt->execute([$nr_paturi, $tarif]);
            $_SESSION['message'] = "Tipul de cameră a fost adăugat cu succes.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Eroare la adăugarea tipului de cameră: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Numărul de paturi și tariful trebuie să fie mai mari decât zero.";
    }

    header("Location: tip_camera.php");
    exit;
}

// --- 2. GESTIONARE ȘTERGERE TIP CAMERĂ ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        // Restricția ON DELETE RESTRICT din db.php asigură că nu putem șterge un tip dacă e folosit de o cameră
        $stmt = $pdo->prepare("DELETE FROM tip_camera WHERE id_tip_camera = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
             $_SESSION['message'] = "Tipul de cameră a fost șters cu succes.";
        } else {
             $_SESSION['error'] = "Eroare: Tipul de cameră nu poate fi șters deoarece este folosit de camere existente.";
        }
       
    } catch (PDOException $e) {
        // Dacă eroarea este din cauza restricției (foreign key), afișăm un mesaj relevant
        if ($e->getCode() === '23000') {
             $_SESSION['error'] = "Eroare: Tipul de cameră nu poate fi șters deoarece există camere asociate acestuia.";
        } else {
             $_SESSION['error'] = "Eroare la ștergere: " . $e->getMessage();
        }
    }
    header("Location: tip_camera.php");
    exit;
}

// Preluare lista tipuri de camere
$tipuri_camera = $pdo->query("SELECT * FROM tip_camera ORDER BY nr_paturi, tarif_pe_loc")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Tipuri Cameră</title>
    <style>
        body { background: transparent; font-family: Arial, sans-serif; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1); margin-bottom: 25px; }
        h2 { color: #059669; border-bottom: 3px solid #10b981; padding-bottom: 10px; margin-bottom: 25px; }
        
        /* Formularea */
        .form-add { display: flex; gap: 15px; padding: 20px; border: 1px solid #d1fae5; border-radius: 10px; background: #f0fdfa; }
        .form-group { flex-grow: 1; }
        .form-group label { display: block; font-weight: 600; color: #059669; margin-bottom: 5px; }
        
        .form-add input {
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
        .badge { background-color: #34d399; color: white; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 0.85em; }
    </style>
</head>
<body>

<div class="container">
    <h2>Definire Tipuri de Camere și Tarife</h2>

    <!-- Mesaje de Stare -->
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- Formular adăugare Tip Cameră -->
    <form method="POST" class="form-add">
        <div class="form-group">
            <label for="nr_paturi">Număr Locuri în Cameră:</label>
            <input type="number" name="nr_paturi" id="nr_paturi" min="1" placeholder="Ex: 2 sau 4" required>
        </div>
        <div class="form-group">
            <label for="tarif">Tarif Lunar pe Loc (RON):</label>
            <input type="number" name="tarif" id="tarif" step="0.01" min="0.01" placeholder="Ex: 150.00" required>
        </div>
        <input type="submit" name="add" value="Adaugă Tip Cameră" class="btn-submit">
    </form>

    <!-- Tabel Tipuri Camere -->
    <table>
        <thead>
            <tr>
                <th>ID Tip</th>
                <th>Descriere</th>
                <th>Nr. Locuri/Cameră</th>
                <th>Tarif pe Loc (RON)</th>
                <th>Acțiuni</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tipuri_camera)): ?>
                <tr><td colspan="5" style="text-align: center;">Nu există tipuri de camere definite.</td></tr>
            <?php else: ?>
                <?php foreach ($tipuri_camera as $t): ?>
                    <tr>
                        <td><?= $t['id_tip_camera'] ?></td>
                        <td><span class="badge">Cameră cu <?= $t['nr_paturi'] ?> Locuri</span></td>
                        <td><?= $t['nr_paturi'] ?></td>
                        <td><?= number_format($t['tarif_pe_loc'], 2) ?></td>
                        <td>
                            <a href="?delete=<?= $t['id_tip_camera'] ?>"
                               onclick="return confirm('Sigur ștergi acest tip de cameră? Toate camerele asociate vor trebui modificate!');">
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