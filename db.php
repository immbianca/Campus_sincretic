<?php

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "campus_db"; // Numele bazei de date

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "
    CREATE DATABASE IF NOT EXISTS $dbname
      CHARACTER SET utf8mb4
      COLLATE utf8mb4_unicode_ci;

    USE $dbname;

    CREATE TABLE IF NOT EXISTS facultate (
      id_facultate INT AUTO_INCREMENT PRIMARY KEY,
      nume_facultate VARCHAR(100) NOT NULL
    );

    CREATE TABLE IF NOT EXISTS tip_camera (
      id_tip_camera INT AUTO_INCREMENT PRIMARY KEY,
      nr_paturi INT NOT NULL,
      tarif_pe_loc DECIMAL(10,2) NOT NULL
    );

    CREATE TABLE IF NOT EXISTS camin (
      id_camin INT AUTO_INCREMENT PRIMARY KEY,
      nume_camin VARCHAR(100) NOT NULL,
      id_facultate INT NOT NULL,
      FOREIGN KEY (id_facultate) REFERENCES facultate(id_facultate) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS camera (
      id_camera INT AUTO_INCREMENT PRIMARY KEY,
      id_camin INT NOT NULL,
      id_tip_camera INT NOT NULL,
      nr_camera VARCHAR(20) NOT NULL, /* Adaugat nr_camera */
      nr_locuri_total INT NOT NULL,
      FOREIGN KEY (id_camin) REFERENCES camin(id_camin) ON DELETE CASCADE,
      FOREIGN KEY (id_tip_camera) REFERENCES tip_camera(id_tip_camera) ON DELETE RESTRICT
    );

    CREATE TABLE IF NOT EXISTS student (
      id_student INT AUTO_INCREMENT PRIMARY KEY,
      nume VARCHAR(60) NOT NULL,
      prenume VARCHAR(60) NOT NULL,
      CNP CHAR(13),
      anul_studiu INT,
      id_facultate INT,
      FOREIGN KEY (id_facultate) REFERENCES facultate(id_facultate) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS repartizare (
      id_repartizare INT AUTO_INCREMENT PRIMARY KEY,
      id_student INT NOT NULL,
      id_camera INT NOT NULL,
      data_repartizare DATE NOT NULL,
      activ TINYINT(1) DEFAULT 1,
      FOREIGN KEY (id_student) REFERENCES student(id_student) ON DELETE CASCADE,
      FOREIGN KEY (id_camera) REFERENCES camera(id_camera) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS chitanta (
      id_chitanta INT AUTO_INCREMENT PRIMARY KEY,
      id_student INT NOT NULL,
      perioada VARCHAR(80),
      data_emiterii DATE,
      suma_platita DECIMAL(10,2) NOT NULL,
      FOREIGN KEY (id_student) REFERENCES student(id_student) ON DELETE CASCADE
    );

    -- Inserare date de exemplu (Optional, doar pentru testare initiala)
    INSERT IGNORE INTO facultate (id_facultate, nume_facultate) VALUES 
      (1, 'Mecanica'), 
      (2, 'Chimie'), 
      (3, 'Automatica si Calculatoare'),
      (4, 'Matematică'),
      (5, 'Fizică'),
      (6, 'Biologie'),
      (7, 'Științe Economice'),
      (8, 'Litere');

    INSERT IGNORE INTO tip_camera (id_tip_camera, nr_paturi, tarif_pe_loc) VALUES 
      (1, 2, 500.00), 
      (2, 3, 400.00), 
      (3, 4, 350.00),
      (4, 1, 600.00),
      (5, 5, 300.00),
      (6, 6, 250.00);

    INSERT IGNORE INTO camin (id_camin, nume_camin, id_facultate) VALUES 
      (101, 'Camin 1', 1), 
      (102, 'Camin 3', 2),
      (103, 'Camin 4', 2),
      (104, 'Camin 5', 3),
      (105, 'Camin 2', 1),
      (106, 'Camin 6', 4),
      (107, 'Camin 7', 5),
      (108, 'Camin 8', 6),
      (109, 'Camin 9', 7),
      (110, 'Camin 10', 8);
      
    INSERT IGNORE INTO camera (id_camera, id_camin, id_tip_camera, nr_camera, nr_locuri_total) VALUES
        (1001, 101, 1, '101A', 2), 
        (1002, 101, 2, '102A', 3), 
        (1003, 102, 3, '201B', 4), 
        (1004, 104, 1, '110C', 2),
        (1005, 101, 3, '103A', 4),
        (1006, 102, 2, '202B', 3),
        (1007, 103, 1, '301C', 2),
        (1008, 104, 4, '111C', 1),
        (1009, 105, 5, '201A', 5),
        (1010, 106, 6, '101D', 6),
        (1011, 107, 1, '102E', 2),
        (1012, 108, 3, '201F', 4),
        (1013, 109, 2, '101G', 3),
        (1014, 110, 4, '102H', 1),
        (1015, 101, 5, '104A', 5),
        (1016, 102, 6, '203B', 6),
        (1017, 103, 3, '302C', 4),
        (1018, 104, 2, '112C', 3),
        (1019, 105, 1, '202A', 2),
        (1020, 106, 4, '102D', 1);

    -- Studenți de exemplu
    INSERT IGNORE INTO student (id_student, nume, prenume, CNP, anul_studiu, id_facultate) VALUES
        (1, 'Popescu', 'Andrei', '1990101010001', 3, 1),
        (2, 'Ionescu', 'Maria', '2000202020002', 2, 2),
        (3, 'Vasilescu', 'Elena', '2010303030003', 1, 3),
        (4, 'Dumitrescu', 'Ion', '1985121512345', 4, 4),
        (5, 'Stan', 'Ana', '1999050612346', 2, 5),
        (6, 'Georgescu', 'Mihai', '2001121212347', 1, 6),
        (7, 'Petrescu', 'Laura', '1997080812348', 3, 7),
        (8, 'Radu', 'Cristian', '2000030312349', 2, 8),
        (9, 'Marinescu', 'Diana', '1996111112350', 4, 1),
        (10, 'Tudor', 'Alexandru', '2002070712351', 1, 2),
        (11, 'Constantin', 'Raluca', '1999030412352', 3, 3),
        (12, 'Enache', 'Bogdan', '2001090912353', 2, 4),
        (13, 'Florea', 'Simona', '1998060612354', 4, 5),
        (14, 'Gheorghe', 'Vlad', '2000040212355', 1, 6),
        (15, 'Horia', 'Ioana', '1997121212356', 3, 7),
        (16, 'Ilie', 'Daniel', '2001050512357', 2, 8),
        (17, 'Jianu', 'Gabriela', '1999101012358', 4, 1),
        (18, 'Kovacs', 'Robert', '2002010112359', 1, 2),
        (19, 'Lupu', 'Monica', '1998070712360', 3, 3),
        (20, 'Munteanu', 'Florin', '2000080812361', 2, 4);
        
    -- Repartizări de exemplu
    INSERT IGNORE INTO repartizare (id_repartizare, id_student, id_camera, data_repartizare, activ) VALUES
        (1, 1, 1001, '2024-09-01', 1),
        (2, 2, 1003, '2024-09-01', 1),
        (3, 3, 1004, '2024-09-01', 1),
        (4, 4, 1005, '2024-09-01', 1),
        (5, 5, 1006, '2024-09-01', 1),
        (6, 6, 1007, '2024-09-01', 1),
        (7, 7, 1008, '2024-09-01', 1),
        (8, 8, 1009, '2024-09-01', 1),
        (9, 9, 1010, '2024-09-01', 1),
        (10, 10, 1011, '2024-09-01', 1),
        (11, 11, 1012, '2024-09-01', 1),
        (12, 12, 1013, '2024-09-01', 1),
        (13, 13, 1014, '2024-09-01', 1),
        (14, 14, 1015, '2024-09-01', 1),
        (15, 15, 1016, '2024-09-01', 1),
        (16, 16, 1017, '2024-09-01', 1),
        (17, 17, 1018, '2024-09-01', 1),
        (18, 18, 1019, '2024-09-01', 1),
        (19, 19, 1020, '2024-09-01', 1),
        (20, 20, 1001, '2024-09-01', 0); 

    -- Chitanțe de exemplu
    INSERT IGNORE INTO chitanta (id_chitanta, id_student, perioada, data_emiterii, suma_platita) VALUES
        (1, 1, 'Semestrul I', '2024-09-05', 2500.00),
        (2, 2, 'Semestrul I', '2024-09-05', 1400.00),
        (3, 3, 'Semestrul I', '2024-09-05', 500.00),
        (4, 4, 'Semestrul I', '2024-09-05', 1400.00),
        (5, 5, 'Semestrul I', '2024-09-05', 1200.00),
        (6, 6, 'Semestrul I', '2024-09-05', 500.00),
        (7, 7, 'Semestrul I', '2024-09-05', 600.00),
        (8, 8, 'Semestrul I', '2024-09-05', 1500.00),
        (9, 9, 'Semestrul I', '2024-09-05', 1500.00),
        (10, 10, 'Semestrul I', '2024-09-05', 500.00),
        (11, 11, 'Semestrul I', '2024-09-05', 1400.00),
        (12, 12, 'Semestrul I', '2024-09-05', 1200.00),
        (13, 13, 'Semestrul I', '2024-09-05', 600.00),
        (14, 14, 'Semestrul I', '2024-09-05', 1500.00),
        (15, 15, 'Semestrul I', '2024-09-05', 1500.00),
        (16, 16, 'Semestrul I', '2024-09-05', 1400.00),
        (17, 17, 'Semestrul I', '2024-09-05', 1200.00),
        (18, 18, 'Semestrul I', '2024-09-05', 500.00),
        (19, 19, 'Semestrul I', '2024-09-05', 600.00),
        (20, 20, 'Semestrul I', '2024-09-05', 600.00);

    ";

    $pdo->exec($sql);
} catch (PDOException $e) {
    echo "Eroare la crearea bazei de date: " . $e->getMessage();
}

?>