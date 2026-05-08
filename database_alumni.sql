-- 1. Πίνακας για τους Αποφοίτους
CREATE TABLE alumni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    firstname VARCHAR(50) NOT NULL,
    lastname VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL, -- Θα χρειαστεί για το Login με JWT
    entry_year INT NOT NULL,        -- Έτος εισαγωγής
    grad_year INT NOT NULL,         -- Έτος ολοκλήρωσης σπουδών
    country VARCHAR(50) NOT NULL    -- Χώρα (για το Google Chart)
);

-- 2. Πίνακας για τις Θέσεις Εργασίας
CREATE TABLE jobs (
    job_id INT AUTO_INCREMENT PRIMARY KEY,
    alumni_id INT,                  -- Σύνδεση με τον απόφοιτο
    company_name VARCHAR(100),
    job_title VARCHAR(100),
    city VARCHAR(50),
    lat DECIMAL(10, 8),             -- Γεωγραφικό πλάτος για τον χάρτη
    lng DECIMAL(11, 8),             -- Γεωγραφικό μήκος για τον χάρτη
    FOREIGN KEY (alumni_id) REFERENCES alumni(id) ON DELETE CASCADE
);