<?php
// ============================================================
// Slim Framework 4 | JWT Authentication | MySQL/PDO
// 
// ENDPOINTS:
//   POST   /api/v1/alumni           → #1 Εγγραφή αποφοίτου
//   POST   /api/v1/login            → #2 Σύνδεση & έκδοση JWT
//   GET    /api/v1/alumni/count     → #3 Πλήθος αποφοίτων
//   GET    /api/v1/alumni/{id}/jobs → #4 Εργασίες αποφοίτου
//   GET    /api/v1/alumni           → #5 Όλοι οι απόφοιτοι
//   DELETE /api/v1/jobs             → #6 Διαγραφή εργασίας
//   PUT    /api/v1/jobs             → #7 Προσθήκη/Ενημέρωση εργασίας
//   GET    /api/v1/search           → #8 Αναζήτηση με φίλτρα (JSON & XML)
//   GET    /api/v1/jobs/myjob       → Εργασία συνδεδεμένου χρήστη
//   GET    /api/v1/cities           → Λίστα πόλεων για το dropdown
// ============================================================
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Firebase\JWT\JWT;

require __DIR__ . '/vendor/autoload.php';
require_once 'db_config.php'; // Σύνδεση με τη βάση δεδομένων ($conn)

/** @var \PDO $conn */ 
global $conn;         

/* ============================== ΑΡΧΙΚΟΠΟΙΗΣΗ ΕΦΑΡΜΟΓΗΣ & MIDDLEWARE ============================== */
$app = AppFactory::create();
$app->addBodyParsingMiddleware(); // Middleware για αυτόματο parsing του JSON/XML body των requests

// OPTIONS route - πρέπει να είναι το ΠΡΩΤΟ route Ο browser στέλνει OPTIONS (preflight) πριν από PUT/DELETE για να ελέγξει αν επιτρέπεται το CORS
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response->withStatus(200);
});

// Routing middleware - ΜΕΤΑ το options route
$app->addRoutingMiddleware();

// CORS middleware - τελευταίο (εκτελείται πρώτο λόγω LIFO)
$app->add(function (Request $request, $handler) {
    $method = $request->getMethod();

    // Πιάνει ΟΛΑ τα possible formats - Αν είναι OPTIONS preflight, απάντα άμεσα χωρίς να φτάσει στο routing
    if ($method === 'OPTIONS' || strtoupper($method) === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withStatus(200);
    }
    // Για όλα τα άλλα requests, πρόσθεσε τα CORS headers στο response
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});
/* ============================== ROUTE: Αρχική Σελίδα - Σερβίρει το index.html (το frontend της εφαρμογής) ============================== */
$app->get('/', function (Request $request, Response $response) {
    $html = file_get_contents('index.html');
    $response->getBody()->write($html);
    return $response;
});
/* ============================== ROUTE #1: Εγγραφή Νέου Αποφοίτου - POST /api/v1/alumni ============================== */
$app->post('/api/v1/alumni', function (Request $request, Response $response) use ($conn) {
    $data = $request->getParsedBody();
    
    // Έλεγχος αν το email υπάρχει ήδη
    $check = $conn->prepare("SELECT id FROM alumni WHERE email = ?");
    $check->execute([$data['email']]);
    if ($check->fetch()) {
        $response->getBody()->write(json_encode(["status" => "error", "message" => "Το email υπάρχει ήδη"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Κρυπτογράφηση κωδικού με bcrypt
    $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);

    try {
        $sql = "INSERT INTO alumni (firstname, lastname, email, password, entry_year, grad_year, country, city) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $data['firstname'],
            $data['lastname'],
            $data['email'],
            $hashedPassword,
            $data['entry_year'],
            $data['grad_year'],
            $data['country'],
            $data['city']
        ]);

        $response->getBody()->write(json_encode(["status" => "success"]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    } catch (Exception $e) {
        $response->getBody()->write(json_encode(["status" => "error", "message" => $e->getMessage()]));
        return $response->withStatus(500);
    }
});
/* ============================== ROUTE #2: Σύνδεση Χρήστη (Login) & Έκδοση JWT Token - POST /api/v1/login ============================== */
// Μυστικό κλειδί για υπογραφή/επαλήθευση JWT tokens
$jwt_secret = "YOUR_SUPER_SECRET_GOD_ALMIGHTY_KEY_123"; // Κλειδί κρυπτογράφησης

$app->post('/api/v1/login', function (Request $request, Response $response) use ($conn, $jwt_secret) {
    $data = $request->getParsedBody();
    $email = $data['email'] ?? ''; 
    $password = $data['password'] ?? '';
    // Αναζήτηση χρήστη στη βάση με βάση το email
    $stmt = $conn->prepare("SELECT * FROM alumni WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Επαλήθευση κωδικού: συγκρίνει plaintext με bcrypt hash
    if ($user && password_verify($password, $user['password'])) {
        // Δημιουργία JWT payload
        $payload = [
            'iat' => time(), // Χρόνος έκδοσης
            'exp' => time() + (3600 * 1), // Λήξη σε 1 ώρα
            'sub' => $user['id'], // ID χρήστη (subject)
            'email' => $user['email']
        ];

        // Κωδικοποίηση token με HS256
        $token = JWT::encode($payload, $jwt_secret, 'HS256');

        $response->getBody()->write(json_encode([
        "status" => "success",
        "token" => $token,
        "user" => [
            "firstname" => $user['firstname'],
            "lastname" => $user['lastname']
        ]
    ]));
    return $response->withHeader('Content-Type', 'application/json');
}

    // Λάθος στοιχεία σύνδεσης
    $response->getBody()->write(json_encode([
        "status"  => "error",
        "message" => "Λανθασμένο email ή κωδικός"
    ]));
    return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
});
/* ============================== ROUTE #3: Πλήθος Αποφοίτων - GET /api/v1/alumni/count ============================== */
$app->get('/api/v1/alumni/count', function (Request $request, Response $response) use ($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM alumni");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    $response->getBody()->write(json_encode($count));
    return $response->withHeader('Content-Type', 'application/json');
});
/* ============================== ROUTE #4: Εργασίες Συγκεκριμένου Αποφοίτου - GET /api/v1/alumni/{id}/jobs ============================== */
$app->get('/api/v1/alumni/{id}/jobs', function (Request $request, Response $response, array $args) use ($conn) {
    $alumni_id = $args['id'];
    try {
        $stmt = $conn->prepare("SELECT * FROM jobs WHERE alumni_id = ?");
        $stmt->execute([$alumni_id]);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response->getBody()->write(json_encode($jobs, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (Exception $e) {
        $response->getBody()->write(json_encode(["error" => $e->getMessage()]));
        return $response->withStatus(500);
    }
});
/* ============================== ROUTE #5: Λήψη Όλων των Αποφοίτων - GET /api/v1/alumni ============================== */
$app->get('/api/v1/alumni', function (Request $request, Response $response) use ($conn) {
    $sql = "SELECT a.*, j.company_name, j.job_title, j.city as job_city, j.lat, j.lng 
            FROM alumni a LEFT JOIN jobs j ON a.id = j.alumni_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $alumni = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $response->getBody()->write(json_encode($alumni, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});
/* ============================== ROUTE #6: Διαγραφή Εργασίας (Protected) - DELETE /api/v1/jobs ============================== */
$app->delete('/api/v1/jobs', function (Request $request, Response $response) use ($conn, $jwt_secret) {
    $auth = $request->getHeaderLine('Authorization');
    $token = str_replace('Bearer ', '', $auth);
    
    try {
        $decoded = JWT::decode($token, new \Firebase\JWT\Key($jwt_secret, 'HS256'));
        $userId = $decoded->sub;

        // Έλεγχος αν υπάρχει όντως η εγγραφή πριν τη διαγραφή
        $check = $conn->prepare("SELECT job_id FROM jobs WHERE alumni_id = ?");
        $check->execute([$userId]);
        
        if (!$check->fetch()) {
            $response->getBody()->write(json_encode(["status" => "error", "message" => "Δεν βρέθηκε εργασία προς διαγραφή"]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Διαγραφή εργασίας
        $conn->prepare("DELETE FROM jobs WHERE alumni_id = ?")->execute([$userId]);
        
        $response->getBody()->write(json_encode(["status" => "success"]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (Exception $e) {
        return $response->withStatus(401);
    }
});
/* ============================== ROUTE #7: Προσθήκη / Ενημέρωση Εργασίας (Protected) - PUT /api/v1/jobs ============================== */
$app->put('/api/v1/jobs', function (Request $request, Response $response) use ($conn, $jwt_secret) {
    $auth = $request->getHeaderLine('Authorization');
    $token = str_replace('Bearer ', '', $auth);

    try {
        // Χρήση της κλάσης Key για την αποκωδικοποίηση
        $decoded = JWT::decode($token, new \Firebase\JWT\Key($jwt_secret, 'HS256'));
        $userId = $decoded->sub; // Το ID του χρήστη από το Token
        $data = $request->getParsedBody();

        // Έλεγχος αν υπάρχει ήδη εργασία για αυτόν τον απόφοιτο
        $stmt = $conn->prepare("SELECT job_id FROM jobs WHERE alumni_id = ?");
        $stmt->execute([$userId]);
        $exists = $stmt->fetch();

        if ($exists) { // Ενημέρωση υπάρχουσας εργασίας
            $sql = "UPDATE jobs SET company_name=?, job_title=?, city=?, lat=?, lng=? WHERE alumni_id=?";
            $conn->prepare($sql)->execute([$data['company'], $data['title'], $data['city'], $data['lat'], $data['lng'], $userId]);
        } else { // Εισαγωγή νέας εργασίας
            $sql = "INSERT INTO jobs (alumni_id, company_name, job_title, city, lat, lng) VALUES (?, ?, ?, ?, ?, ?)";
            $conn->prepare($sql)->execute([$userId, $data['company'], $data['title'], $data['city'], $data['lat'], $data['lng']]);
        }

        $response->getBody()->write(json_encode(["status" => "success"]));
        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) { // Αποτυχία επαλήθευσης token (ληγμένο, λάθος υπογραφή κλπ)
        $response->getBody()->write(json_encode(["error" => $e->getMessage()]));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }
});
/* ============================== ROUTE #8: Αναζήτηση Αποφοίτων με Φίλτρα & Σελιδοποίηση - GET /api/v1/search ============================== */
$app->get('/api/v1/search', function (Request $request, Response $response) use ($conn) {
    $params = $request->getQueryParams();
    $format = $params['format'] ?? 'json'; // Προεπιλογή το json

    // Παράμετροι αναζήτησης (κενή τιμή = αγνοείται)
    $lastname   = $params['lastname'] ?? '';
    $city       = $params['city'] ?? '';
    $entry_year = $params['entry_year'] ?? '';
    $grad_year  = $params['grad_year'] ?? '';
    $country    = $params['country'] ?? '';
    $page = (int)($params['page'] ?? 1);
    $limit = 4;
    $offset = ($page - 1) * $limit;

    // --- Βήμα 1: Μέτρηση συνολικών αποτελεσμάτων για pagination ---
    $countSql = "SELECT COUNT(*) as total FROM alumni a LEFT JOIN jobs j ON a.id = j.alumni_id WHERE 1=1";
    $p = [];
    if ($lastname)   { $countSql .= " AND a.lastname LIKE ?";    $p[] = "%$lastname%"; }
    if ($city)       { $countSql .= " AND j.city = ?";           $p[] = $city; }
    if ($entry_year) { $countSql .= " AND a.entry_year = ?";     $p[] = $entry_year; }
    if ($grad_year)  { $countSql .= " AND a.grad_year = ?";      $p[] = $grad_year; }
    if ($country)    { $countSql .= " AND a.country LIKE ?";     $p[] = "%$country%"; }

    $stmt = $conn->prepare($countSql);
    $stmt->execute($p);
    $totalRecords = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Αν δεν υπάρχει κανένα φίλτρο, επιστρέφουμε όλους χωρίς pagination
    $isSearchEmpty = empty($lastname) && empty($city) && empty($entry_year) && empty($grad_year) && empty($country);
    $currentLimit = $isSearchEmpty ? $totalRecords : $limit;
    $totalPages = $isSearchEmpty ? 1 : ceil($totalRecords / $limit);

    // --- Βήμα 2: Λήψη αποτελεσμάτων με τα ίδια φίλτρα ---
    $sql = "SELECT a.*, j.company_name, j.job_title, j.city as job_city, j.lat, j.lng 
            FROM alumni a LEFT JOIN jobs j ON a.id = j.alumni_id WHERE 1=1";
    if ($lastname)   { $sql .= " AND a.lastname LIKE ?"; }
    if ($city)       { $sql .= " AND j.city = ?"; }
    if ($entry_year) { $sql .= " AND a.entry_year = ?"; }
    if ($grad_year)  { $sql .= " AND a.grad_year = ?"; }
    if ($country)    { $sql .= " AND a.country LIKE ?"; }
    $sql .= " LIMIT $currentLimit OFFSET $offset";

    $stmt = $conn->prepare($sql);
    $stmt->execute($p);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Δεδομένα pagination
    $output = [
        "pagination" => [
            "total_records" => $totalRecords, 
            "total_pages" => $totalPages, 
            "current_page" => $page
        ],
        "data" => $results
    ];
    
    // --- Βήμα 3: Επιστροφή σε XML ή JSON ανάλογα με το ?format ---
    if (strtolower($format) === 'xml') {
    // Κατασκευή XML response με SimpleXML
    $xml = new SimpleXMLElement('<results/>');
    $pag = $xml->addChild('pagination');
    $pag->addChild('total_records', $totalRecords);
    $pag->addChild('total_pages', $totalPages);
    $pag->addChild('current_page', $page);
    
    $dataNode = $xml->addChild('data');
    foreach ($results as $row) {
        $alumni = $dataNode->addChild('alumni');
        foreach ($row as $key => $value) {
            // htmlspecialchars για αποφυγή προβλημάτων με ειδικούς χαρακτήρες
            $alumni->addChild($key, htmlspecialchars($value ?? ''));
        }
    }
    
    $response->getBody()->write($xml->asXML());
    return $response->withHeader('Content-Type', 'application/xml');
} else { // Προεπιλογή: JSON response
    $response->getBody()->write(json_encode($output, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
}
});
/* ============================== ROUTE: Εργασία Συνδεδεμένου Χρήστη (Protected) - GET /api/v1/jobs/myjob ============================== */
$app->get('/api/v1/jobs/myjob', function (Request $request, Response $response) use ($conn, $jwt_secret) {
    // Παίρνουμε το Token από το Authorization Header
    $auth = $request->getHeaderLine('Authorization');
    $token = str_replace('Bearer ', '', $auth);
    
    try { // Αποκωδικοποίηση JWT για να βρούμε το ID του απόφοιτου
        $decoded = JWT::decode($token, new \Firebase\JWT\Key($jwt_secret, 'HS256'));
        $userId = $decoded->sub;

        // Αναζήτηση εργασίας στην βάση με βάση το alumni_id
        $stmt = $conn->prepare("SELECT * FROM jobs WHERE alumni_id = ?");
        $stmt->execute([$userId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        // Επιστρέφει τα στοιχεία εργασίας ή false αν δεν υπάρχει
        $response->getBody()->write(json_encode($job));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (Exception $e) {
        return $response->withStatus(401);
    }
});
/* ============================== ROUTE: Δυναμική Λήψη Πόλεων - GET /api/v1/cities ============================== */
$app->get('/api/v1/cities', function (Request $request, Response $response) use ($conn) {
    $stmt = $conn->prepare("SELECT DISTINCT city FROM jobs WHERE city != '' ORDER BY city ASC");
    $stmt->execute();
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $response->getBody()->write(json_encode($cities, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});
$app->run();