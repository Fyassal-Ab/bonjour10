<?php
try {
    $pdo = new PDO(
        "mysql:host=localhost;port=3306;dbname=resume_db;charset=utf8mb4",
        "root",
        "FF@2002@aa"
    );
    echo "Connected!";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
