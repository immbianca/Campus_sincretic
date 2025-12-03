<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "campus_db";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} 
catch (PDOException $e) {
    if ($e->getCode() == 1049) {

        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec("
            CREATE DATABASE IF NOT EXISTS $dbname
            CHARACTER SET utf8mb4
            COLLATE utf8mb4_unicode_ci;
        ");

        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "
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
          nr_camera VARCHAR(20),
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
        ";

        $pdo->exec($sql);
    } 
    else {
        die("Database error: " . $e->getMessage());
    }
}
?>
