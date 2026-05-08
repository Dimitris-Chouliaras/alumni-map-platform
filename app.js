let map;                   // Αντικείμενο Google Maps
let activeMarkers = [];    // Λίστα ενεργών markers στον χάρτη
let currentPage = 1;       // Τρέχουσα σελίδα pagination
let totalPages = 1;        // Συνολικές σελίδες pagination
let hasJob = false;        // Αν ο συνδεδεμένος χρήστης έχει καταχωρημένη εργασία
let oms;                   // OverlappingMarkerSpiderfier (για επικαλυπτόμενους markers)
/* ============================================================ ΑΡΧΙΚΟΠΟΙΗΣΗ ΧΑΡΤΗ & SPIDERFIER ============================================================ */
async function initMap() {
    const { Map } = await google.maps.importLibrary("maps");
    // Δημιουργία χάρτη με κέντρο την Ελλάδα
    map = new Map(document.getElementById("map"), {
        zoom: 7,
        center: { lat: 38.9, lng: 22.5 },
        mapId: "DEMO_MAP_ID", 
    });

    // Αρχικοποίηση του Spiderfier - χειρίζεται markers που βρίσκονται στο ίδιο σημείο, "ανοίγοντάς" τους σαν αράχνη για να φαίνονται
    oms = new OverlappingMarkerSpiderfier(map, {
        markersWontMove: true,
        markersWontHide: true,
        keepSpiderfied: true,
        nearbyDistance: 20,      // Pixels απόσταση για να θεωρηθούν "κοντά"
        legWeight: 3.5,          // Πάχος γραμμών spiderfier
        circleFootSeparation: 35 // Απόσταση μεταξύ markers όταν ανοίγουν
    });

    // Αλλαγή εικονιδίου marker ανάλογα με την κατάστασή του
    oms.addListener('format', function(marker, status) {
        if (status === OverlappingMarkerSpiderfier.markerStatus.SPIDERFIABLE) {
            // ΜΟΝΟ αν υπάρχουν πολλοί markers στο ίδιο σημείο — πορτοκαλί χρώμα
            marker.setTitle("Υπάρχουν περισσότεροι απόφοιτοι εδώ. Κάντε κλικ για εμφάνιση.");
            marker.setIcon('http://maps.google.com/mapfiles/ms/icons/red-dot.png'); 
        } else {
            // Αν είναι μόνος του (μεμονωμένος marker) — κόκκινο χρώμα, επαναφορά ονόματος
            marker.setTitle(marker.originalTitle); // Επαναφορά στο όνομα του αποφοίτου
            marker.setIcon('http://maps.google.com/mapfiles/ms/icons/orange-dot.png');
        }
    });

    // Click handler για markers — εμφανίζει στοιχεία αποφοίτου - το ορίζουμε μόνο μια φορά για όλη τη διάρκεια ζωής της σελίδας
    oms.addListener('click', function(marker) {
            alert(marker.desc);
    });

    // Φόρτωση αρχικών δεδομένων σελίδας
    loadCities();
    loadAlumniCount();
    checkAuthStatus();
}
/* ============================================================ ΦΟΡΤΩΣΗ ΔΕΔΟΜΕΝΩΝ ============================================================ */
// Λήψη και εμφάνιση συνολικού πλήθους αποφοίτων (Endpoint #3)
async function loadAlumniCount() {
    try {
        const response = await fetch('api/v1/alumni/count');
        const result = await response.json();
        document.getElementById('alumniCount').innerText = result.total;
    } catch (e) { console.error("Error loading count", e); }
}

// Δυναμικό γέμισμα του dropdown με τις διαθέσιμες πόλεις εργασίας
async function loadCities() {
    try {
        const response = await fetch('api/v1/cities');
        const cities = await response.json();
        const select = document.getElementById('searchCity');
        cities.forEach(city => {
            let opt = document.createElement('option');
            opt.value = city;
            opt.innerHTML = city;
            select.appendChild(opt);
        });
    } catch (e) { console.error("Error loading cities", e); }
}
/* ============================================================ ΑΝΑΖΗΤΗΣΗ & MARKERS ΧΑΡΤΗ ============================================================ */
// Στέλνει τα φίλτρα στο API και τοποθετεί markers στον χάρτη (Endpoint #8)
async function searchAlumni(page) {
    currentPage = page;
    // Συλλογή τιμών φίλτρων
    const lastname = document.getElementById('searchName').value;
    const city = document.getElementById('searchCity').value;
    const entryYear = document.getElementById('searchEntryYear').value;
    const gradYear = document.getElementById('searchGradYear').value;
    const country = document.getElementById('searchCountry').value;

    // Κατασκευή URL με query parameters
    const url = `api/v1/search?lastname=${encodeURIComponent(lastname)}&city=${encodeURIComponent(city)}&entry_year=${encodeURIComponent(entryYear)}&grad_year=${encodeURIComponent(gradYear)}&country=${encodeURIComponent(country)}&page=${currentPage}`;

    try {
        const response = await fetch(url);
        const result = await response.json();
        const alumniData = result.data;
        totalPages = result.pagination.total_pages;

        // Καθαρισμός παλιών markers από τον χάρτη και τον Spiderfier
        activeMarkers.forEach(m => m.setMap(null));
        activeMarkers = [];
        oms.clearMarkers(); 

        // Τοποθέτηση νέων markers για κάθε απόφοιτο με συντεταγμένες
        alumniData.forEach(person => {
            if (person.lat && person.lng) {
                // Δημιουργούμε το πλήρες ονοματεπώνυμο
                const fullName = `${person.lastname} ${person.firstname}`;

                const marker = new google.maps.Marker({
                    map: map,
                    position: { lat: parseFloat(person.lat), lng: parseFloat(person.lng) },
                    title: fullName // Εμφανίζεται ως tooltip στην αρχή
                });

                // Αποθήκευση ονόματος για επαναφορά από τον Spiderfier
                marker.originalTitle = fullName; 
                
                // Πλήρες κείμενο που εμφανίζεται στο alert κλικ
                marker.desc = `Απόφοιτος: ${fullName}\n` +
                      `Εργασία: ${person.job_title} στην εταιρεία ${person.company_name || 'Μη καταχωρημένη'}\n` +
                      `Πόλη εργασίας: ${person.job_city || 'Μη καταχωρημένη'}\n` +
                      `Πόλη καταγωγής: ${person.city || 'Μη καταχωρημένη'}`;
                
                oms.addMarker(marker);
                activeMarkers.push(marker);
            }
        });

        // Αυτόματο zoom στην πόλη αν επιλέχθηκε φίλτρο πόλης
        if (city && alumniData.length > 0) {
            map.setCenter({ lat: parseFloat(alumniData[0].lat), lng: parseFloat(alumniData[0].lng) });
            map.setZoom(12);
        }

        updatePaginationUI();
    } catch (error) { console.error("Search Error:", error); }
}
/* ============================================================ PAGINATION ============================================================ */
// Ενημέρωση εμφάνισης κουμπιών σελιδοποίησης
function updatePaginationUI() {
    const controls = document.getElementById('pagination-controls');
    if (totalPages > 1) {
        controls.classList.remove('d-none');
        document.getElementById('pageInfo').innerText = `${currentPage} / ${totalPages}`;
        document.getElementById('btnPrev').disabled = (currentPage === 1);
        document.getElementById('btnNext').disabled = (currentPage === totalPages);
    } else {
        controls.classList.add('d-none');
    }
}

// Αλλαγή σελίδας (+1 επόμενη, -1 προηγούμενη)
function changePage(step) {
    const newPage = currentPage + step;
    if (newPage >= 1 && newPage <= totalPages) { searchAlumni(newPage); }
}
/* ============================================================ GOOGLE CHARTS ============================================================ */
// Donut chart κατανομής αποφοίτων ανά πόλη εργασίας
google.charts.load('current', {'packages':['corechart']});
async function drawChart() {
    try {
        const response = await fetch('api/v1/alumni');
        const data = await response.json();

        // Μέτρηση αποφοίτων ανά πόλη εργασίας
        const cityCounts = {};
        data.forEach(item => {
            const cityName = item.job_city || "Χωρίς Εργασία";
            cityCounts[cityName] = (cityCounts[cityName] || 0) + 1;
        });

        // Μετατροπή σε μορφή Google Charts
        const chartData = [['Πόλη', 'Πλήθος']];
        for (const city in cityCounts) { 
            chartData.push([city, cityCounts[city]]); 
        }

        const dataTable = google.visualization.arrayToDataTable(chartData);

        // Εδώ ορίζουμε τα options ξεχωριστά για να είναι πιο εύκολο να γίνει κάποια αλλαγή
        const options = {
            pieHole: 0.4,
            chartArea: { width: '90%', height: '75%', top: 10, bottom: 40 },
            legend: { position: 'bottom', textStyle: { fontSize: 10 } },
            backgroundColor: 'transparent',
            height: 250
        };

        const chart = new google.visualization.PieChart(document.getElementById('chart_div'));        
        // Καλούμε το draw χρησιμοποιώντας τη μεταβλητή options
        chart.draw(dataTable, options);

    } catch (e) { console.error("Chart Error:", e); }
}
// Φόρτωση γραφήματος μόλις ετοιμαστεί η βιβλιοθήκη
google.charts.setOnLoadCallback(drawChart);
/* ============================================================ ΚΑΘΑΡΙΣΜΟΣ ΑΝΑΖΗΤΗΣΗΣ ============================================================ */
// Μηδενίζει όλα τα φίλτρα και επαναφέρει τον χάρτη
function clearSearch() {
    // Καθαρισμός πεδίων φίλτρων
    document.getElementById('searchName').value = '';
    document.getElementById('searchCity').value = '';
    document.getElementById('searchEntryYear').value = '';
    document.getElementById('searchGradYear').value = '';
    document.getElementById('searchCountry').value = '';
    // Αφαίρεση markers από χάρτη
    activeMarkers.forEach(m => m.setMap(null));
    activeMarkers = [];
    // Απόκρυψη pagination
    document.getElementById('pagination-controls').classList.add('d-none');
    // Επαναφορά χάρτη στην αρχική θέση (Ελλάδα)
    map.setCenter({ lat: 38.9, lng: 22.5 });
    map.setZoom(7);
}
/* ============================================================ AUTHENTICATION ============================================================ */
// --- Εγγραφή --- Ζητούμενο #1 - Εγγραφή Νέου Αποφοίτου
async function handleRegister(event) {
    event.preventDefault(); // Αποτροπή default submit (page refresh)
    
    const formData = {
        firstname: document.getElementById('regFirstname').value,
        lastname: document.getElementById('regLastname').value,
        email: document.getElementById('regEmail').value,
        password: document.getElementById('regPass').value,
        entry_year: document.getElementById('regEntryYear').value,
        grad_year: document.getElementById('regGradYear').value,
        country: document.getElementById('regCountry').value,
        city: document.getElementById('regCity').value
    };

    const response = await fetch('api/v1/alumni', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    });

    const result = await response.json();

    if (result.status === 'success') {
        alert("Η εγγραφή ολοκληρώθηκε με επιτυχία! Μπορείτε τώρα να συνδεθείτε.");
            loadAlumniCount(); // Ενημέρωση μετρητή αποφοίτων
        bootstrap.Modal.getInstance(document.getElementById('registerModal')).hide();
    } else {
        alert("Σφάλμα: " + (result.error || "Προσπαθήστε ξανά"));
    }
}
// --- Login --- Ζητούμενο #2 - Στέλνει email/password στο API και αποθηκεύει το JWT token
async function handleLogin() {
    const email = document.getElementById('loginUser').value; 
    const pass = document.getElementById('loginPass').value;
    const errorDiv = document.getElementById('loginError');
    
    try {
        const response = await fetch('api/v1/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email, password: pass })
        });

        const result = await response.json();

        if (response.ok && result.status === 'success') {
            // Αποθήκευση JWT token και στοιχείων χρήστη στο localStorage
            localStorage.setItem('adminToken', result.token);
            localStorage.setItem('userData', JSON.stringify(result.user));
            alert('Συνδεθήκατε με επιτυχία!');
            location.reload(); // Ανανέωση σελίδας για να αλλάξει το UI (π.χ. εμφάνιση Logout)
            
            // Κλείσιμο του modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
            modal.hide();

        } else {
            // Εμφάνιση μηνύματος σφάλματος κάτω από τη φόρμα (401)
            errorDiv.innerText = result.message || "Αποτυχία σύνδεσης";
            errorDiv.classList.remove('d-none');
        }
    } catch (e) {
        console.error("Login error", e);
        errorDiv.innerText = "Σφάλμα επικοινωνίας με τον διακομιστή";
        errorDiv.classList.remove('d-none');
    }
}
// --- Logout - Λειτουργία Αποσύνδεσης ---
function handleLogout() {
    localStorage.removeItem('adminToken');
    localStorage.removeItem('userData');
    alert("Αποσυνδεθήκατε!");
    location.reload();
}

// --- Έλεγχος Κατάστασης Σύνδεσης - Εκτελείται κατά τη φόρτωση (ενημερώνει το UI ανάλογα με το αν υπάρχει αποθηκευμένο token στο localStorage) ---
function checkAuthStatus() {
    const token = localStorage.getItem('adminToken');
    const guestZone = document.getElementById('guestZone');
    const userZone = document.getElementById('userZone');
    const userNameDisplay = document.getElementById('userNameDisplay');

    if (token) { // Ο χρήστης είναι συνδεδεμένος — εμφάνιση userZone
        const userData = JSON.parse(localStorage.getItem('userData'));
        if (userData) {
            userNameDisplay.innerText = `${userData.firstname} ${userData.lastname}`;
            guestZone.classList.add('d-none');
            userZone.classList.remove('d-none');
            
            // Εμφάνιση κουμπιού διαγραφής αν είμαστε συνδεδεμένοι
            const delBtn = document.getElementById('btnDeleteJob');
            if(delBtn) delBtn.classList.remove('d-none');
        }
    }
    // Αν δεν υπάρχει token, το guestZone παραμένει ορατό (default)
}
/* ============================================================ ΔΙΑΧΕΙΡΙΣΗ ΕΡΓΑΣΙΑΣ ============================================================ */
// --- Φόρτωση Στοιχείων Εργασίας - Καλείται όταν ο χρήστης ανοίξει το modal "Η Εργασία μου". Προφορτώνει τα πεδία με τα υπάρχοντα δεδομένα από το API ---
async function loadMyJobData() {
    const token = localStorage.getItem('adminToken');
    if (!token) return;

    try {
        const response = await fetch('api/v1/jobs/myjob', {
            method: 'GET',
            headers: { 'Authorization': 'Bearer ' + token }
        });

        if (response.ok) {
            const data = await response.json();
            
            const delBtn = document.getElementById('btnDeleteJob');

            if (data && data.job_id) { // Κοιτάμε αν υπάρχει εργασία για τον χρήστη
                hasJob = true; // Ο χρήστης έχει ήδη εργασία — γέμισε τα πεδία
                document.getElementById('jobCompany').value = data.company_name || '';
                document.getElementById('jobTitle').value = data.job_title || '';
                document.getElementById('jobCity').value = data.city || '';
                document.getElementById('jobLat').value = data.lat || '';
                document.getElementById('jobLng').value = data.lng || '';
                
                if (delBtn) delBtn.classList.remove('d-none'); // Εμφάνιση κουμπιού
            } else {
                hasJob = false; // Δεν υπάρχει εργασία — καθάρισε τα πεδία
                document.getElementById('jobCompany').value = data.company_name || '';
                document.getElementById('jobTitle').value = data.job_title || '';
                document.getElementById('jobCity').value = data.city || '';
                document.getElementById('jobLat').value = data.lat || '';
                document.getElementById('jobLng').value = data.lng || '';
                if (delBtn) delBtn.classList.add('d-none'); // Απόκρυψη κουμπιού διαγραφής
            }
        }
    } catch (error) { console.error(error); }
}

// --- Αποθήκευση / Ενημέρωση Εργασίας (Endpoint #7) - Αν ο χρήστης έχει ήδη εργασία -> UPDATE, αλλιώς -> INSERT ---
async function saveJob() {
    const token = localStorage.getItem('adminToken');

    if (!token) {
        alert("Δεν είστε συνδεδεμένοι! Token not found.");
        return;
    }

    // Συλλογή δεδομένων φόρμας
    const jobData = {
        company: document.getElementById('jobCompany').value,
        title: document.getElementById('jobTitle').value,
        city: document.getElementById('jobCity').value,
        lat: document.getElementById('jobLat').value,
        lng: document.getElementById('jobLng').value
    };

    // Έλεγχος υποχρεωτικών πεδίων
    if (!jobData.city || !jobData.lat || !jobData.lng) {
        alert("Παρακαλώ συμπληρώστε Πόλη και Συντεταγμένες!");
        return;
    }
    
    const response = await fetch('api/v1/jobs', {
        method: 'PUT',
        headers: { 
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}` 
        },
        body: JSON.stringify(jobData)
    });

    if (response.ok) {
        alert("Η εργασία σας αποθηκεύτηκε επιτυχώς!");
        location.reload();
    } else {
        alert("Σφάλμα κατά την αποθήκευση. Βεβαιωθείτε ότι είστε συνδεδεμένοι.");
    }
}

// --- Διαγραφή Εργασίας (Endpoint #6) ---
async function deleteJob() {
    if (!hasJob) { // Αν δεν υπάρχει εργασία, δεν γίνεται διαγραφή
        alert("Δεν υπάρχει καταχωρημένη εργασία προς διαγραφή!");
        return;
    }

    if (!confirm("Είστε σίγουροι για τη διαγραφή της εργασίας σας;")) return;
    
    const token = localStorage.getItem('adminToken');
    const response = await fetch('api/v1/jobs', {
        method: 'DELETE',
        headers: { 'Authorization': 'Bearer ' + token }
    });

    if (response.ok) {
        alert("Η εργασία διαγράφηκε επιτυχώς!");
        hasJob = false; // Ενημέρωση της μεταβλητής
        location.reload();
    }
}
/* ============================================================ ΒΟΗΘΗΤΙΚΕΣ ΣΥΝΑΡΤΗΣΕΙΣ ============================================================ */
// --- "Ματάκι" για το password ---
function togglePassword(inputId, btn) {
    const passwordInput = document.getElementById(inputId);
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        btn.innerHTML = '🙈'; // Εικονίδιο απόκρυψης
    } else {
        passwordInput.type = 'password';
        btn.innerHTML = '👁️'; // Εικονίδιο εμφάνισης
    }
}

// Κάνει το γράφημα να ξανασχεδιάζεται σωστά όταν αλλάζει το μέγεθος του παραθύρου
window.addEventListener('resize', drawChart);