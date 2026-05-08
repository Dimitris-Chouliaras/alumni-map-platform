<?php
// ============================================================
// db_config.php - Σύνδεση με τη Βάση Δεδομένων
// Χρησιμοποιεί PDO για ασφαλή και αξιόπιστη σύνδεση με MySQL
// ============================================================

// --- Στοιχεία Σύνδεσης ---
$host     = "localhost";
$db_name  = "alumni_db";
$username = "root";
$password = ""; // Στο XAMPP το default password είναι κενό

try {
    // Δημιουργία σύνδεσης PDO με UTF-8 encoding για σωστή υποστήριξη ελληνικών
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    
    // Ρύθμιση ώστε να πετάει exceptions αν κάτι πάει στραβά (αντί να αποτυγχάνει σιωπηλά)
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch(PDOException $exception) {
    // Αν η σύνδεση αποτύχει, εμφάνισε το σφάλμα και σταμάτα την εκτέλεση
    die("Σφάλμα σύνδεσης με τη βάση δεδομένων: " . $exception->getMessage());
}
?>