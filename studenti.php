<?php
include "db.php";

if (isset($_POST['add'])) {
    $nume      = trim($_POST['nume'] ?? '');
    $prenume   = trim($_POST['prenume'] ?? '');
    $cnp       = trim($_POST['cnp'] ?? '');
    $an        = intval($_POST['an'] ?? 1);
    $facultate = intval($_POST['facultate'] ?? 0);

    if ($nume !== '' && $prenume !== '' && $facultate > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO student (nume, prenume, CNP, anul_studiu, id_facultate)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nume, $prenume, $cnp, $an, $facultate]);
    }
    header("Location: studenti.php");
    exit;
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM student WHERE id_student = ?");
    $stmt->execute([$id]);
    header("Location: studenti.php");
    exit;
}

$facultati = $pdo->query("SELECT * FROM facultate ORDER BY nume_facultate")->fetchAll(PDO::FETCH_ASSOC);
$camine    = $pdo->query("SELECT * FROM camin ORDER BY nume_camin")->fetchAll(PDO::FETCH_ASSOC);

$filtru_facultate = isset($_GET['facultate_filter']) ? intval($_GET['facultate_filter']) : 0;
$filtru_camin     = isset($_GET['camin_filter']) ? intval($_GET['camin_filter']) : 0;
$search           = trim($_GET['search'] ?? '');

$where   = [];
$params  = [];

if ($filtru_facultate > 0) {
    $where[]  = 's.id_facultate = ?';
    $params[] = $filtru_facultate;
}

if ($filtru_camin > 0) {
    $where[]  = 'ca.id_camin = ?';
    $params[] = $filtru_camin;
}

if ($search !== '') {
    $where[] = '(s.nume LIKE ? OR s.prenume LIKE ? OR CONCAT(s.nume, " ", s.prenume) LIKE ?)';
    $like    = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$where_sql = '';
if (!empty($where)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

$sql_studenti = "
    SELECT 
        s.id_student,
        s.nume,
        s.prenume,
        s.CNP,
        s.anul_studiu,
        f.nume_facultate,
        ca.nume_camin,
        cam.nr_camera
    FROM student s
    LEFT JOIN facultate f ON s.id_facultate = f.id_facultate
    LEFT JOIN repartizare r ON s.id_student = r.id_student AND r.activ = 1
    LEFT JOIN camera cam ON r.id_camera = cam.id_camera
    LEFT JOIN camin ca ON cam.id_camin = ca.id_camin
    $where_sql
    ORDER BY s.id_student DESC
";
$stmt_studenti = $pdo->prepare($sql_studenti);
$stmt_studenti->execute($params);
$studenti = $stmt_studenti->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Studenți</title>
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
            grid-template-columns: 1.3fr 2.5fr;
            gap: 20px;
        }

        .card {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px 18px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }

        h3 {
            margin-top: 0;
            margin-bottom: 12px;
        }

        form {
            margin: 0;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }

        .form-row label {
            font-size: 13px;
            margin-bottom: 3px;
            display: block;
        }

        .field {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 140px;
        }

        input[type="text"],
        select {
            padding: 7px 9px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
            outline: none;
        }

        input[type="text"]:focus,
        select:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 1px rgba(16,185,129,0.35);
        }

        .btn {
            padding: 8px 14px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background: #10b981;
            color: white;
        }
        .btn-primary:hover {
            background: #0e8f6d;
        }

        .btn-danger {
            background: #e11d48;
            color: white;
        }
        .btn-danger:hover {
            background: #b31038;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th, td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }

        th {
            background: #10b981;
            color: white;
            text-align: left;
        }

        tr:nth-child(even) td {
            background: #f9fafb;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: flex-end;
        }

        .filters .field {
            min-width: 160px;
        }

        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 11px;
            background: #eef2ff;
            color: #4c1d95;
        }

        @media (max-width: 900px) {
            .layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<h2 class="page-title">Evidență Studenți</h2>

<div class="layout">

    <div class="card">
        <h3>Adaugă student</h3>
        <form method="POST">
            <div class="form-row">
                <div class="field">
                    <label for="nume">Nume</label>
                    <input type="text" id="nume" name="nume" required>
                </div>
                <div class="field">
                    <label for="prenume">Prenume</label>
                    <input type="text" id="prenume" name="prenume" required>
                </div>
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="cnp">CNP</label>
                    <input type="text" id="cnp" name="cnp" maxlength="13">
                </div>
                <div class="field">
                    <label for="an">An de studiu</label>
                    <select id="an" name="an" required>
                        <option value="1">Anul 1</option>
                        <option value="2">Anul 2</option>
                        <option value="3">Anul 3</option>
                        <option value="4">Anul 4</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="facultate">Facultate</label>
                    <select id="facultate" name="facultate" required>
                        <option value="">Alege facultate</option>
                        <?php foreach ($facultati as $f): ?>
                            <option value="<?= $f['id_facultate'] ?>">
                                <?= htmlspecialchars($f['nume_facultate']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" name="add" class="btn btn-primary">Adaugă student</button>
        </form>
    </div>

    <div class="card">
        <h3>Lista studenților</h3>

        <form method="GET" class="filters">
            <div class="field">
                <label for="facultate_filter">Filtru facultate</label>
                <select name="facultate_filter" id="facultate_filter">
                    <option value="0">Toate facultățile</option>
                    <?php foreach ($facultati as $f): ?>
                        <option value="<?= $f['id_facultate'] ?>" 
                            <?= ($filtru_facultate == $f['id_facultate']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['nume_facultate']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="camin_filter">Filtru cămin</label>
                <select name="camin_filter" id="camin_filter">
                    <option value="0">Toate căminele</option>
                    <?php foreach ($camine as $c): ?>
                        <option value="<?= $c['id_camin'] ?>"
                            <?= ($filtru_camin == $c['id_camin']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nume_camin']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="search">Căutare nume</label>
                <input type="text" id="search" name="search" placeholder="Nume sau prenume" 
                       value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="field" style="flex:0 0 auto;">
                <button type="submit" class="btn btn-primary">Aplică filtre</button>
            </div>
        </form>

        <table>
            <tr>
                <th>ID</th>
                <th>Student</th>
                <th>CNP</th>
                <th>An</th>
                <th>Facultate</th>
                <th>Cămin</th>
                <th>Camera</th>
                <th>Acțiuni</th>
            </tr>

            <?php if (empty($studenti)): ?>
                <tr>
                    <td colspan="8">Nu există studenți pentru filtrele selectate.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($studenti as $s): ?>
                <tr>
                    <td><?= $s['id_student'] ?></td>
                    <td><?= htmlspecialchars($s['nume'] . " " . $s['prenume']) ?></td>
                    <td><?= htmlspecialchars($s['CNP']) ?></td>
                    <td>
                        <span class="badge">
                            Anul <?= (int)$s['anul_studiu'] ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($s['nume_facultate'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($s['nume_camin'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($s['nr_camera'] ?? '-') ?></td>
                    <td>
                        <a href="?delete=<?= $s['id_student'] ?>" 
                           onclick="return confirm('Sigur ștergi acest student?');">
                            <button type="button" class="btn btn-danger btn-small">Șterge</button>
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
