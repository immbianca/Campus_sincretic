<?php
// Pornim sesiunea pentru a gestiona mesajele de stare (succes/eroare)
session_start();
include "db.php";
/** @var PDO $pdo */

$error_message = $_SESSION['error'] ?? '';
$success_message = $_SESSION['message'] ?? '';
unset($_SESSION['error'], $_SESSION['message']); 

if (isset($_POST['add'])) {
    $nume = trim($_POST['nume'] ?? '');
    if ($nume != "") {
        try {
            $stmt = $pdo->prepare("INSERT INTO facultate (nume_facultate) VALUES (?)");
            $stmt->execute([$nume]);
            $_SESSION['message'] = "Facultatea a fost adăugată cu succes.";
        } catch (PDOException $e) {
             $_SESSION['error'] = "Eroare la adăugarea facultății: " . $e->getMessage();
        }
    } else {
         $_SESSION['error'] = "Numele facultății nu poate fi gol.";
    }
    header("Location: facultati.php");
    exit;
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $pdo->prepare("DELETE FROM facultate WHERE id_facultate = ?")->execute([$id]);
        $_SESSION['message'] = "Facultatea a fost ștearsă cu succes. Căminele asociate au fost, de asemenea, șterse.";
    } catch (PDOException $e) {
         $_SESSION['error'] = "Eroare la ștergerea facultății: " . $e->getMessage();
    }
    header("Location: facultati.php");
    exit;
}

$facultati = $pdo->query("SELECT * FROM facultate ORDER BY id_facultate DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Facultăți</title>
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

        /* Formulare - PENTRU ALINIERE */
        .form-container { 
            display: flex; 
            gap: 15px; 
            margin-bottom: 20px; 
            align-items: center; 
            padding: 20px;
            border: 1px solid #d1fae5;
            border-radius: 10px;
            background: #f0fdfa;
        }
        .form-container input[type="text"] { 
            padding: 10px; 
            border: 1px solid #34d399; 
            border-radius: 8px; 
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
            min-width: 250px;
            flex-grow: 1; /* Permite inputului să ocupe spațiul disponibil */
        }

        /* Butoane */
        .btn { 
            padding: 10px 15px; 
            background: #059669; /* Nuanța Smarald principală */
            border: none; 
            color: white; 
            cursor: pointer; 
            border-radius: 8px;
            font-weight: bold;
            transition: background 0.3s;
            box-shadow: 0 4px 6px rgba(5, 150, 105, 0.2);
        }
        .btn:hover { 
            background: #047857; /* Verde mai închis la hover */
        }
        .btn-danger { 
            background: #ef4444; 
        }
        .btn-danger:hover { 
            background: #dc2626; 
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        .action-buttons a {
            text-decoration: none;
        }

        /* Tabel */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden; /* Pentru a asigura marginile rotunjite ale tabelului */
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
        tr:hover {
            background-color: #eff6ff;
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
    <h2>Gestionare Facultăți</h2>

    <!-- Mesaje de Stare -->
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>


    <form method="POST" class="form-container">
        <input type="text" name="nume" placeholder="Nume facultate" required>
        <button type="submit" name="add" class="btn">Adaugă</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nume Facultate</th>
                <th>Acțiuni</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($facultati)): ?>
                <tr><td colspan="3" style="text-align: center; color: #6b7280;">Nu există facultăți înregistrate.</td></tr>
            <?php else: ?>
                <?php foreach ($facultati as $f): ?>
                    <tr>
                        <td><?= $f['id_facultate'] ?></td>
                        <td><strong><?= htmlspecialchars($f['nume_facultate']) ?></strong></td>
                        <td class="action-buttons">
                            <a href="?delete=<?= $f['id_facultate'] ?>" 
                               onclick="return confirm('Sigur ștergi această facultate? Toate căminele asociate vor fi șterse!');">
                                <button class="btn-small btn-danger">Șterge</button>
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