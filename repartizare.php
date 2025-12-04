<?php
session_start();
include "db.php";

$error_message = $_SESSION['error'] ?? '';
$success_message = $_SESSION['message'] ?? '';
unset($_SESSION['error'], $_SESSION['message']); 

/**
 * Calculează numărul de locuri rămase libere într-o cameră dată.
 * @param PDO $pdo Conexiunea la baza de date.
 * @param int $id_camera ID-ul camerei de verificat.
 * @return int Numărul de locuri disponibile.
 */
function get_locuri_libere($pdo, $id_camera) {
    $stmt_ocupate = $pdo->prepare("
        SELECT COUNT(id_student) AS ocupate
        FROM repartizare
        WHERE id_camera = ? AND activ = 1
    ");
    $stmt_ocupate->execute([$id_camera]);
    $ocupate = $stmt_ocupate->fetch(PDO::FETCH_COLUMN);

    $stmt_total = $pdo->prepare("
        SELECT nr_locuri_total
        FROM camera
        WHERE id_camera = ?
    ");
    $stmt_total->execute([$id_camera]);
    $total = $stmt_total->fetch(PDO::FETCH_COLUMN);

    return $total - $ocupate;
}


if (isset($_POST['repartizeaza'])) {
    $id_student = intval($_POST['id_student'] ?? 0);
    $id_camera = intval($_POST['id_camera'] ?? 0);
    $data_repartizare = date('Y-m-d');

    if ($id_student > 0 && $id_camera > 0) {
        $stmt_existenta = $pdo->prepare("
            SELECT id_repartizare, id_camera
            FROM repartizare
            WHERE id_student = ? AND activ = 1
        ");
        $stmt_existenta->execute([$id_student]);
        $repartizare_existenta = $stmt_existenta->fetch(PDO::FETCH_ASSOC);

        $locuri_libere = get_locuri_libere($pdo, $id_camera);

        $este_mutare = ($repartizare_existenta && $repartizare_existenta['id_camera'] != $id_camera);
        $este_noua = (!$repartizare_existenta);
        $este_aceeasi = ($repartizare_existenta && $repartizare_existenta['id_camera'] == $id_camera);
        
        if ($este_aceeasi) {
             $_SESSION['message'] = "Studentul este deja repartizat în camera selectată. Nu s-a făcut nicio modificare.";
        } elseif ($locuri_libere > 0 || $este_mutare) { 
            
            if ($este_mutare) {
                $stmt_dezactiveaza = $pdo->prepare("
                    UPDATE repartizare 
                    SET activ = 0 
                    WHERE id_repartizare = ?
                ");
                $stmt_dezactiveaza->execute([$repartizare_existenta['id_repartizare']]);
                
                $locuri_ramase = get_locuri_libere($pdo, $id_camera) - 1; 

            } else { 
                $locuri_ramase = $locuri_libere - 1;
            }
            
            $stmt_noua = $pdo->prepare("
                INSERT INTO repartizare (id_student, id_camera, data_repartizare, activ)
                VALUES (?, ?, ?, 1)
            ");
            $stmt_noua->execute([$id_student, $id_camera, $data_repartizare]);
            
            if ($este_mutare) {
                 $_SESSION['message'] = "Studentul a fost mutat cu succes! Locuri libere rămase în camera nouă: " . $locuri_ramase;
            } else { 
                 $_SESSION['message'] = "Repartizare nouă efectuată cu succes! Locuri libere rămase: " . $locuri_ramase;
            }

        } else {
             $_SESSION['error'] = "Eroare la repartizare/mutare: Camera selectată este plină!";
        }
        
    } else {
        $_SESSION['error'] = "Eroare: Trebuie să selectați un student și o cameră.";
    }

    header("Location: repartizare.php");
    exit;
}

if (isset($_GET['anuleaza'])) {
    $id_repartizare = intval($_GET['anuleaza']);
    
    try {
        $stmt = $pdo->prepare("
            UPDATE repartizare 
            SET activ = 0 
            WHERE id_repartizare = ?
        ");
        $stmt->execute([$id_repartizare]);
        
        $_SESSION['message'] = "Repartizarea a fost anulată cu succes. Studentul este acum nerepartizat.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Eroare la anularea repartizării: " . $e->getMessage();
    }

    header("Location: repartizare.php");
    exit;
}

$studenti_toti = $pdo->query("
    SELECT 
        s.id_student, 
        s.nume, 
        s.prenume, 
        f.nume_facultate,
        r.id_repartizare,
        cam.nume_camin,
        c.nr_camera
    FROM student s
    LEFT JOIN facultate f ON s.id_facultate = f.id_facultate
    LEFT JOIN repartizare r ON s.id_student = r.id_student AND r.activ = 1
    LEFT JOIN camera c ON r.id_camera = c.id_camera
    LEFT JOIN camin cam ON c.id_camin = cam.id_camin
    ORDER BY s.nume, s.prenume
")->fetchAll(PDO::FETCH_ASSOC);

$camere_disponibile_sql = "
    SELECT 
        c.id_camera, 
        c.nr_camera, 
        cam.nume_camin,
        tc.tarif_pe_loc,
        c.nr_locuri_total - (
            SELECT COUNT(*) 
            FROM repartizare r 
            WHERE r.id_camera = c.id_camera AND r.activ = 1
        ) AS locuri_libere
    FROM camera c
    JOIN camin cam ON c.id_camin = cam.id_camin
    JOIN tip_camera tc ON c.id_tip_camera = tc.id_tip_camera
    HAVING locuri_libere > 0
    ORDER BY cam.nume_camin, c.nr_camera
";

$camere_disponibile = $pdo->query($camere_disponibile_sql)->fetchAll(PDO::FETCH_ASSOC);

$repartizari_active = $pdo->query("
    SELECT 
        r.id_repartizare, 
        s.nume, 
        s.prenume, 
        s.id_student,
        f.nume_facultate, 
        cam.nume_camin, 
        c.nr_camera, 
        r.data_repartizare,
        tc.tarif_pe_loc
    FROM repartizare r
    JOIN student s ON r.id_student = s.id_student
    LEFT JOIN facultate f ON s.id_facultate = f.id_facultate
    JOIN camera c ON r.id_camera = c.id_camera
    JOIN camin cam ON c.id_camin = cam.id_camin
    JOIN tip_camera tc ON c.id_tip_camera = tc.id_tip_camera
    WHERE r.activ = 1
    ORDER BY cam.nume_camin, c.nr_camera
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Repartizare Studenți</title>
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
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1); 
            margin-bottom: 25px; 
        }
        h2 { 
            color: #059669; 
            border-bottom: 3px solid #10b981; 
            padding-bottom: 10px; 
            margin-bottom: 25px; 
        }
        h3 {
             color: #374151;
             margin-top: 10px;
             margin-bottom: 20px;
        }

        .form-repartizare {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            padding: 20px;
            border: 1px solid #d1fae5;
            border-radius: 10px;
            background: #f0fdfa; 
        }
        
        .form-repartizare > div {
            flex-grow: 1;
            min-width: 280px;
        }
        
        .form-repartizare label {
            display: block;
            font-weight: 600;
            color: #059669;
            margin-bottom: 5px;
        }

        .form-repartizare select {
            padding: 10px;
            border: 1px solid #34d399;
            border-radius: 8px;
            width: 100%;
            background-color: #ffffff;
            appearance: none; 
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        optgroup {
            font-weight: bold;
            color: #374151;
            font-style: italic;
        }

        .form-repartizare .btn-submit {
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
        
        .form-repartizare .btn-submit:hover {
            background: #0d9467;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: background 0.3s;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        
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
        
        .badge { 
            background-color: #34d399; 
            color: white; 
            padding: 4px 8px; 
            border-radius: 4px; 
            font-size: 0.85em; 
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Gestionare Repartizări Cămine</h2>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <form method="POST" class="form-repartizare">
        <h3>Adaugă Repartizare sau Mută un Student</h3>
        
        <div class="form-group">
            <label for="id_student">Selectează Studentul:</label>
            <select name="id_student" id="id_student" required>
                <option value="">-- Selectează Student --</option>
                <?php 
                $nerepartizati_count = 0;
                $repartizati_count = 0;
                ?>
                <optgroup label="Studenți Nerepartizați">
                    <?php foreach ($studenti_toti as $s): ?>
                        <?php if (!$s['id_repartizare']): $nerepartizati_count++; ?>
                            <option value="<?= $s['id_student'] ?>">
                                <?= htmlspecialchars($s['nume'] . " " . $s['prenume'] . " (Fac: " . ($s['nume_facultate'] ?? '-') . ")") ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($nerepartizati_count === 0): ?>
                         <option disabled>Toți studenții sunt repartizați.</option>
                    <?php endif; ?>
                </optgroup>
                <optgroup label="Studenți Repartizați (pentru Mutare)">
                    <?php foreach ($studenti_toti as $s): ?>
                        <?php if ($s['id_repartizare']): $repartizati_count++; ?>
                            <option value="<?= $s['id_student'] ?>">
                                <?= htmlspecialchars($s['nume'] . " " . $s['prenume'] . " (Actual: " . $s['nume_camin'] . " - Cam. " . $s['nr_camera'] . ")") ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($repartizati_count === 0): ?>
                         <option disabled>Niciun student repartizat de mutat.</option>
                    <?php endif; ?>
                </optgroup>
            </select>
        </div>

        <div class="form-group">
            <label for="id_camera">Selectează Camera Destinație:</label>
            <select name="id_camera" id="id_camera" required>
                <option value="">-- Selectează Cameră Disponibilă --</option>
                <?php foreach ($camere_disponibile as $c): ?>
                    <option value="<?= $c['id_camera'] ?>">
                        <?= htmlspecialchars($c['nume_camin'] . " - Camera " . $c['nr_camera'] . " (Libere: " . $c['locuri_libere'] . ")") ?>
                    </option>
                <?php endforeach; ?>
                 <?php if (empty($camere_disponibile)): ?>
                    <option disabled>Nicio cameră disponibilă.</option>
                <?php endif; ?>
            </select>
        </div>
        
        <input type="submit" name="repartizeaza" value="Repartizează / Mută Student" class="btn-submit">
    </form>
</div>

<div class="container">
    <h3>Lista Repartizărilor Active</h3>
    
    <table>
        <thead>
            <tr>
                <th>ID Student</th>
                <th>Nume Student</th>
                <th>Facultate</th>
                <th>Cămin</th>
                <th>Camera</th>
                <th>Tarif/Loc (Ron)</th>
                <th>Data Repartizării</th>
                <th>Acțiuni</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($repartizari_active)): ?>
                <tr><td colspan="8" style="text-align: center;">Nu există repartizări active în acest moment.</td></tr>
            <?php else: ?>
                <?php foreach ($repartizari_active as $r): ?>
                    <tr>
                        <td><?= $r['id_student'] ?></td>
                        <td><?= htmlspecialchars($r['nume'] . " " . $r['prenume']) ?></td>
                        <td><?= htmlspecialchars($r['nume_facultate'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['nume_camin']) ?></td>
                        <td><span class="badge"><?= htmlspecialchars($r['nr_camera']) ?></span></td>
                        <td><?= number_format($r['tarif_pe_loc'], 2) ?></td>
                        <td><?= date('d.m.Y', strtotime($r['data_repartizare'])) ?></td>
                        <td>
                            <a href="?anuleaza=<?= $r['id_repartizare'] ?>" 
                               onclick="return confirm('Sigur anulezi repartizarea studentului <?= addslashes($r['nume'] . ' ' . $r['prenume']) ?>? Această acțiune nu poate fi anulată!');">
                                <button type="button" class="btn-danger">Anulează</button>
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