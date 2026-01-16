<?php
// Pornim sesiunea pentru a gestiona mesajele de stare (succes/eroare)
session_start();
include "db.php";
/** @var PDO $pdo */

$error_message = $_SESSION['error'] ?? '';
$success_message = $_SESSION['message'] ?? '';
unset($_SESSION['error'], $_SESSION['message']); 

if (isset($_POST['add'])) {
    $nume = trim($_POST['nume_camin'] ?? '');
    $facultate = intval($_POST['id_facultate'] ?? 0);

    if ($nume !== '' && $facultate > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO camin (nume_camin, id_facultate) VALUES (?, ?)");
            $stmt->execute([$nume, $facultate]);
            $_SESSION['message'] = "Căminul a fost adăugat cu succes.";
        } catch (PDOException $e) {
             $_SESSION['error'] = "Eroare la adăugarea căminului: " . $e->getMessage();
        }
    } else {
         $_SESSION['error'] = "Toate câmpurile sunt obligatorii.";
    }

    header("Location: camine.php");
    exit;
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM camin WHERE id_camin = ?");
        $stmt->execute([$id]);
        $_SESSION['message'] = "Căminul a fost șters cu succes. Toate camerele și repartizările asociate au fost anulate.";
    } catch (PDOException $e) {
         $_SESSION['error'] = "Eroare la ștergerea căminului: " . $e->getMessage();
    }
    header("Location: camine.php");
    exit;
}

$facultati = $pdo->query("SELECT * FROM facultate ORDER BY nume_facultate")->fetchAll(PDO::FETCH_ASSOC);

$filtru = isset($_GET['facultate_filter']) ? intval($_GET['facultate_filter']) : 0;

$where = "";
$params = [];

if ($filtru > 0) {
    $where = "WHERE c.id_facultate = ?";
    $params[] = $filtru;
}

// Interogare pentru a prelua căminele împreună cu numărul de camere și locuri
$sql = "
    SELECT 
        c.id_camin, 
        c.nume_camin, 
        f.nume_facultate,
        COUNT(ca.id_camera) AS nr_camere,
        SUM(ca.nr_locuri_total) AS total_locuri
    FROM camin c
    JOIN facultate f ON c.id_facultate = f.id_facultate
    LEFT JOIN camera ca ON c.id_camin = ca.id_camin
    $where
    GROUP BY c.id_camin
    ORDER BY c.nume_camin
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$camine = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Cămine</title>
    <style>
        /* Stiluri de bază */
        body { 
            background: transparent; 
            font-family: Arial, sans-serif; 
            padding: 20px; 
        }
        .container { 
            background: white; 
            padding: 20px; 
            border-radius: 12px; 
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); 
            margin-bottom: 25px;
        }
        h2 { 
            color: #059669; /* Verde închis */
            border-bottom: 3px solid #10b981; 
            padding-bottom: 10px; 
            margin-bottom: 25px; 
        }

        /* Formulare */
        .form-container, .filter-container { 
            display: flex; 
            gap: 15px; 
            margin-bottom: 20px; 
            align-items: center; 
            padding: 20px;
            border: 1px solid #d1fae5;
            border-radius: 10px;
            background: #f0fdfa;
        }
        .form-container input, .form-container select, .filter-container select { 
            padding: 10px; 
            border: 1px solid #34d399; 
            border-radius: 8px; 
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
            min-width: 200px;
        }
        .form-container label {
             font-weight: 600; 
             color: #059669;
        }

        /* Butoane */
        .btn { 
            padding: 10px 15px; 
            background: #10b981; 
            border: none; 
            color: white; 
            cursor: pointer; 
            border-radius: 8px;
            font-weight: bold;
            transition: background 0.3s;
        }
        .btn:hover { 
            background: #0d9467; 
        }
        .btn-danger { 
            background: #ef4444; 
        }
        .btn-danger:hover { 
            background: #dc2626; 
        }
        
        /* NOU: Stil pentru butoanele care erau anterior albastre - Nuanța Smarald */
        .btn-info-green {
            background: #059669; /* Verde Smarald: S-a potrivit cu imaginea */
            color: white; 
            box-shadow: 0 4px 6px rgba(5, 150, 105, 0.2); /* Adaugă o umbră subtilă */
        }
        .btn-info-green:hover {
            background: #047857; /* Verde mai închis la hover */
        }
        /* Am eliminat stilurile .btn-primary care erau albastre */

        .btn-small {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        /* Tabel */
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

        .action-buttons button {
            margin-right: 5px;
        }
        
        /* Mesaje de Stare */
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

    </style>
</head>
<body>

<div class="container">
    <h2>Gestionare Cămine</h2>

    <!-- Mesaje de Stare -->
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- Formular adăugare -->
    <form method="POST" class="form-container">
        <label for="nume_camin">Nume Cămin:</label>
        <input type="text" name="nume_camin" id="nume_camin" placeholder="Ex: Cămin P1" required>
        
        <label for="id_facultate">Facultate Aferentă:</label>
        <select name="id_facultate" id="id_facultate" required>
            <option value="">-- Selectează Facultatea --</option>
            <?php foreach ($facultati as $f): ?>
                <option value="<?= $f['id_facultate'] ?>">
                    <?= htmlspecialchars($f['nume_facultate']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="add" class="btn">Adaugă Cămin</button>
    </form>
    
    <!-- Filtru Cămine -->
    <form method="GET" class="filter-container">
        <label for="facultate_filter">Filtrează după Facultate:</label>
        <select name="facultate_filter" id="facultate_filter" onchange="this.form.submit()">
            <option value="0">Toate Facultățile</option>
            <?php foreach ($facultati as $f): ?>
                <option value="<?= $f['id_facultate'] ?>" <?= $filtru == $f['id_facultate'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($f['nume_facultate']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <!-- AICI S-A SCHIMBAT CLASA: btn-primary -> btn-info-green -->
        <a href="tip_camera.php" style="margin-left: auto;"><button type="button" class="btn btn-info-green">Configurare Tipuri Camere</button></a>
    </form>


    <!-- Tabel cămine -->
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Cămin</th>
                <th>Facultate</th>
                <th>Nr. Camere</th>
                <th>Total Locuri</th>
                <th>Acțiuni</th>
            </tr>
        </thead>

            <?php if (empty($camine)): ?>
                <tr><td colspan="6" style="text-align: center;">Nu există cămine.</td></tr>

            <?php else: ?>
                <?php foreach ($camine as $c): ?>
                    <tr>
                        <td><?= $c['id_camin'] ?></td>
                        <td><strong><?= htmlspecialchars($c['nume_camin']) ?></strong></td>
                        <td><?= htmlspecialchars($c['nume_facultate']) ?></td>
                        <td><?= (int)$c['nr_camere'] ?></td>
                        <td><?= (int)$c['total_locuri'] ?></td>

                        <td class="action-buttons">
                            <!-- AICI S-A SCHIMBAT CLASA: btn-primary -> btn-info-green -->
                            <a href="camere.php?camin=<?= $c['id_camin'] ?>">
                                <button class="btn-small btn-info-green">Camere</button>
                            </a>

                            <a href="?delete=<?= $c['id_camin'] ?>"
                               onclick="return confirm('Sigur ștergi acest cămin? Atenție: Această acțiune șterge toate camerele și repartizările asociate!');">
                                <button class="btn-small btn-danger">Șterge</button>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>

    </div>

</div>

</body>
</html>