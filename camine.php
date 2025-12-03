<?php
include "db.php";

if (isset($_POST['add'])) {
    $nume = trim($_POST['nume_camin'] ?? '');
    $facultate = intval($_POST['id_facultate'] ?? 0);

    if ($nume !== '' && $facultate > 0) {
        $stmt = $pdo->prepare("INSERT INTO camin (nume_camin, id_facultate) VALUES (?, ?)");
        $stmt->execute([$nume, $facultate]);
    }

    header("Location: camine.php");
    exit;
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM camin WHERE id_camin = ?");
    $stmt->execute([$id]);
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

$sql = "
    SELECT 
        c.id_camin,
        c.nume_camin,
        f.nume_facultate,
        COUNT(cam.id_camera) AS nr_camere,
        COALESCE(SUM(cam.nr_locuri_total), 0) AS total_locuri
    FROM camin c
    LEFT JOIN facultate f ON c.id_facultate = f.id_facultate
    LEFT JOIN camera cam ON c.id_camin = cam.id_camin
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
<title>Camine</title>

<style>
body {
    background: transparent;
    font-family: Arial, sans-serif;
    padding: 20px;
}

.page-title {
    font-size: 22px;
    margin-bottom: 15px;
}

.layout {
    display: grid;
    grid-template-columns: 1.2fr 2.5fr;
    gap: 20px;
}

.card {
    background: white;
    padding: 18px;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}

h3 { margin-top: 0; }

input[type="text"],
select {
    width: 100%;
    padding: 7px 8px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    margin-bottom: 10px;
}

button {
    padding: 8px 14px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.btn-primary { background: #10b981; color: white; }
.btn-primary:hover { background: #0e8f6d; }

.btn-danger { background: #e11d48; color: white; }
.btn-danger:hover { background: #b31038; }

.btn-small { padding: 5px 10px; font-size: 13px; }

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
}

th, td {
    padding: 8px 10px;
    border-bottom: 1px solid #e5e7eb;
}

th {
    background: #10b981;
    color: white;
    text-align: left;
}

tr:nth-child(even) td { background: #f9fafb; }

.action-buttons {
    display: flex;
    gap: 6px;
}
</style>
</head>

<body>

<h2 class="page-title">Evidență Cămine</h2>

<div class="layout">

    <div class="card">
        <h3>Adaugă cămin</h3>

        <form method="POST">
            <label>Nume cămin</label>
            <input type="text" name="nume_camin" required>

            <label>Facultate</label>
            <select name="id_facultate" required>
                <option value="">Alege facultate</option>
                <?php foreach ($facultati as $f): ?>
                    <option value="<?= $f['id_facultate'] ?>">
                        <?= htmlspecialchars($f['nume_facultate']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" name="add" class="btn-primary">Adaugă cămin</button>
        </form>
    </div>

    <div class="card">
        <h3>Lista căminelor</h3>

        <form method="GET">
            <select name="facultate_filter" onchange="this.form.submit()">
                <option value="0">Toate facultățile</option>
                <?php foreach ($facultati as $f): ?>
                    <option value="<?= $f['id_facultate'] ?>" 
                        <?= ($filtru == $f['id_facultate']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($f['nume_facultate']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <table>
            <tr>
                <th>ID</th>
                <th>Cămin</th>
                <th>Facultate</th>
                <th>Nr camere</th>
                <th>Total locuri</th>
                <th>Acțiuni</th>
            </tr>

            <?php if (empty($camine)): ?>
                <tr><td colspan="6">Nu există cămine.</td></tr>

            <?php else: ?>
                <?php foreach ($camine as $c): ?>
                    <tr>
                        <td><?= $c['id_camin'] ?></td>
                        <td><?= htmlspecialchars($c['nume_camin']) ?></td>
                        <td><?= htmlspecialchars($c['nume_facultate']) ?></td>
                        <td><?= $c['nr_camere'] ?></td>
                        <td><?= $c['total_locuri'] ?></td>

                        <td class="action-buttons">
                            <a href="camere.php?camin=<?= $c['id_camin'] ?>">
                                <button class="btn-small btn-primary">Camere</button>
                            </a>

                            <a href="?delete=<?= $c['id_camin'] ?>"
                               onclick="return confirm('Sigur ștergi acest cămin?');">
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
