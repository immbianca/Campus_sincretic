<?php
include "db.php";

if (isset($_POST['add'])) {
    $nume = trim($_POST['nume']);
    if ($nume != "") {
        $stmt = $pdo->prepare("INSERT INTO facultate (nume_facultate) VALUES (?)");
        $stmt->execute([$nume]);
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM facultate WHERE id_facultate = ?")->execute([$id]);
}

$facultati = $pdo->query("SELECT * FROM facultate ORDER BY id_facultate DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Facultăți</title>
    <style>
        body {
            background: transparent;
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        .container {
            background: white;
            padding: 20px;
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 10px;
            border-bottom: 1px solid #ccc;
        }

        th {
            background: #10b981;
            color: white;
        }

        input[type="text"] {
            padding: 8px;
            width: 250px;
        }

        button {
            padding: 8px 15px;
            background: #10b981;
            border: none;
            color: white;
            cursor: pointer;
            border-radius: 8px;
        }

        button:hover {
            background: #0e8f6d;
        }

        .delete-btn {
            background: #e11d48;
        }
        .delete-btn:hover {
            background: #b31038;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Gestionare Facultăți</h2>

    <form method="POST">
        <input type="text" name="nume" placeholder="Nume facultate" required>
        <button type="submit" name="add">Adaugă</button>
    </form>

    <table>
        <tr>
            <th>ID</th>
            <th>Nume Facultate</th>
            <th>Acțiuni</th>
        </tr>

        <?php foreach ($facultati as $f): ?>
            <tr>
                <td><?= $f['id_facultate'] ?></td>
                <td><?= htmlspecialchars($f['nume_facultate']) ?></td>
                <td>
                    <a href="?delete=<?= $f['id_facultate'] ?>">
                        <button class="delete-btn">Șterge</button>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>

    </table>
</div>

</body>
</html>
