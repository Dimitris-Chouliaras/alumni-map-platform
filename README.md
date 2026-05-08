# Alumni Mapping & Networking Platform

Μια Single Page Application (SPA) για τη χαρτογράφηση και διασύνδεση αποφοίτων.

## 🚀 Τεχνολογίες
- **Backend:** PHP (Slim Framework 4), PDO, JWT Authentication
- **Frontend:** JavaScript (ES6+), Bootstrap 5, CSS3
- **APIs:** Google Maps API, Google Charts API
- **Database:** MySQL

## ✨ Χαρακτηριστικά
- Αναζήτηση αποφοίτων με φίλτρα και σελιδοποίηση.
- Χάρτης με Spiderfier (διαχείριση επικαλυπτόμενων markers).
- Σύστημα Login με JWT Tokens.
- Δυναμικό Donut Chart για στατιστικά ανά πόλη.
- Πλήρες CRUD για την επαγγελματική κατάσταση του αποφοίτου.

## 📸 Screenshots
<img width="1915" height="1038" alt="6th" src="https://github.com/user-attachments/assets/3437be73-54be-4ebf-b24a-b054e4e0fa79" />

## 🛠️ Οδηγίες Εγκατάστασης (Local Setup)

Για να τρέξετε την εφαρμογή τοπικά, ακολουθήστε τα παρακάτω βήματα:

### 1. Προαπαιτούμενα
Θα πρέπει να έχετε εγκατεστημένα τα εξής:
* **XAMPP / WampServer** (για Apache, PHP 7.4+ και MySQL).
* **Composer** (διαχειριστής πακέτων της PHP).

### 2. Ρύθμιση Βάσης Δεδομένων
1. Ανοίξτε το **phpMyAdmin**.
2. Δημιουργήστε μια νέα βάση δεδομένων με όνομα `alumni_db` (ή όποιο όνομα προτιμάτε).
3. Κάντε **Import** το αρχείο `.sql` (θα το βρείτε στον φάκελο του project) για να δημιουργηθούν οι πίνακες και τα δεδομένα.

### 3. Εγκατάσταση Εξαρτήσεων (Dependencies)
Ανοίξτε το τερματικό στον φάκελο του project και τρέξτε:
```bash
composer install

### 4. Ρύθμιση Κώδικα
Στο αρχείο db_config.php, συμπληρώστε τα στοιχεία της δικής σας βάσης (host, dbname, user, password).

Στο αρχείο index.html, προσθέστε το δικό σας Google Maps API Key στο script tag στο τέλος του αρχείου.

### 5. Εκτέλεση
Μεταφέρετε τον φάκελο στο htdocs (αν χρησιμοποιείτε XAMPP) και επισκεφθείτε τη διεύθυνση:
http://localhost/alumni-project/
