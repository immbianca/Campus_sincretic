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
      (3, 'Automatica si Calculatoare');

    INSERT IGNORE INTO tip_camera (id_tip_camera, nr_paturi, tarif_pe_loc) VALUES 
      (1, 2, 500.00), 
      (2, 3, 400.00), 
      (3, 4, 350.00);

    INSERT IGNORE INTO camin (id_camin, nume_camin, id_facultate) VALUES 
      (101, 'Camin 1', 1), 
      (102, 'Camin 3', 2),
      (103, 'Camin 4', 2),
      (104, 'Camin 5', 3);
      
    INSERT IGNORE INTO camera (id_camera, id_camin, id_tip_camera, nr_camera, nr_locuri_total) VALUES
        (1001, 101, 1, '101A', 2), 
        (1002, 101, 2, '102A', 3), 
        (1003, 102, 3, '201B', 4), 
        (1004, 104, 1, '110C', 2);


    -- Student de exemplu
    INSERT IGNORE INTO student (id_student, nume, prenume, CNP, anul_studiu, id_facultate) VALUES
        (1, 'Popescu', 'Andrei', '1990101010001', 3, 1),
        (2, 'Ionescu', 'Maria', '2000202020002', 2, 2),
        (3, 'Vasilescu', 'Elena', '2010303030003', 1, 3);
        
    -- Repartizare de exemplu
    INSERT IGNORE INTO repartizare (id_repartizare, id_student, id_camera, data_repartizare, activ) VALUES
        (1, 1, 1001, '2024-09-01', 1);

    -- Chitanta de exemplu
    INSERT IGNORE INTO chitanta (id_chitanta, id_student, perioada, data_emiterii, suma_platita) VALUES
        (1, 1, 'Semestrul I', '2024-09-05', 2500.00);


    ";

    $pdo->exec($sql);
} catch (PDOException $e) {
    echo "Eroare la crearea bazei de date: " . $e->getMessage();
}

?>