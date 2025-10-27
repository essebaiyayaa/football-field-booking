<?php
session_start();
require_once '../config/database.php';

// session verificta
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// id terrain
$id_terrain = $_GET['id'] ?? null;

if (!$id_terrain) {
    header('Location: terrains.php');
    exit;
}

// terrain infos
try {
    $stmt = $pdo->prepare("SELECT * FROM Terrain WHERE id_terrain = ?");
    $stmt->execute([$id_terrain]);
    $terrain = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$terrain) {
        header('Location: terrains.php');
        exit;
    }
} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}

// user info
try {
    $stmt = $pdo->prepare("SELECT nom, prenom, email, telephone FROM Utilisateur WHERE id_utilisateur = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}

// les options supplementaires
try {
    $stmt = $pdo->query("SELECT * FROM OptionSupplementaire ORDER BY nom_option");
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $options = [];
}

// traitement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date_reservation = $_POST['date_reservation'];
    $heure_debut = $_POST['heure_debut'];
    $heure_fin = date('H:i:s', strtotime($heure_debut) + 3600); 
    $options_selectionnees = $_POST['options'] ?? [];
    $commentaires = $_POST['commentaires'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // insertion
        $stmt = $pdo->prepare("
            INSERT INTO Reservation (date_reservation, heure_debut, heure_fin, id_utilisateur, id_terrain, commentaires, statut)
            VALUES (?, ?, ?, ?, ?, ?, 'Confirmée')
        ");
        $stmt->execute([$date_reservation, $heure_debut, $heure_fin, $_SESSION['user_id'], $id_terrain, $commentaires]);
        $id_reservation = $pdo->lastInsertId();
        
        // Insertion des options
        if (!empty($options_selectionnees)) {
            $stmt = $pdo->prepare("INSERT INTO Reservation_Option (id_reservation, id_option) VALUES (?, ?)");
            foreach ($options_selectionnees as $id_option) {
                $stmt->execute([$id_reservation, $id_option]);
            }
        }
        
        $pdo->commit();
        
        // redirection vers la facture
        header("Location: facture.php?id=" . $id_reservation);
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Erreur lors de la réservation: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réserver - <?php echo htmlspecialchars($terrain['nom_terrain']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f9fafb;
            color: #333;
        }

        /* Header */
        header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 5%;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
            font-weight: bold;
            color: #16a34a;
            text-decoration: none;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .back-btn:hover {
            color: #16a34a;
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 5%;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        /* Main Form */
        .form-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }

        .terrain-info {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .terrain-info h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .terrain-info p {
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }

        .terrain-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-right: 0.5rem;
            margin-top: 0.5rem;
        }

        .section-title {
            font-size: 1.4rem;
            color: #1f2937;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: #16a34a;
        }

        .user-info {
            background: #f9fafb;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-weight: 600;
            color: #374151;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 600;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #16a34a;
        }

        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 0.75rem;
            margin-top: 0.5rem;
        }

        .time-slot {
            position: relative;
        }

        .time-slot input[type="radio"] {
            display: none;
        }

        .time-slot label {
            display: block;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }

        .time-slot input[type="radio"]:checked + label {
            background: #16a34a;
            color: white;
            border-color: #16a34a;
        }

        .time-slot label:hover {
            border-color: #16a34a;
        }

        .time-slot.disabled label {
            background: #f3f4f6;
            color: #9ca3af;
            cursor: not-allowed;
            border-color: #e5e7eb;
        }

        .time-slot.disabled label:hover {
            border-color: #e5e7eb;
        }

        .no-slots-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            margin-top: 1rem;
        }

        .options-list {
            display: grid;
            gap: 1rem;
        }

        .option-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .option-item:hover {
            border-color: #16a34a;
        }

        .option-item.selected {
            border-color: #16a34a;
            background: #f0fdf4;
        }

        .option-checkbox {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .option-checkbox input {
            width: 1.25rem;
            height: 1.25rem;
            cursor: pointer;
        }

        .option-name {
            font-weight: 600;
            color: #374151;
        }

        .option-price {
            color: #16a34a;
            font-weight: 700;
        }

        /* Sidebar (Panier) */
        .sidebar {
            position: sticky;
            top: 100px;
            height: fit-content;
        }

        .cart {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }

        .cart-title {
            font-size: 1.3rem;
            color: #1f2937;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-name {
            color: #6b7280;
        }

        .cart-item-price {
            font-weight: 600;
            color: #374151;
        }

        .cart-total {
            background: #f0fdf4;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .cart-total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .cart-total-label {
            font-weight: 600;
            color: #065f46;
        }

        .cart-total-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #16a34a;
        }

        .btn-submit {
            width: 100%;
            background: #16a34a;
            color: white;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1rem;
        }

        .btn-submit:hover {
            background: #15803d;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        @media (max-width: 1024px) {
            .container {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <a href="home.php" class="logo">
                <i class="fas fa-futbol"></i>
                FootBooking
            </a>
            <a href="terrains.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Retour aux terrains
            </a>
        </nav>
    </header>

    <div class="container">
        <!-- Formulaire -->
        <div class="form-section">
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Info terrain -->
            <div class="terrain-info">
                <h2><i class="fas fa-futbol"></i> <?php echo htmlspecialchars($terrain['nom_terrain']); ?></h2>
                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($terrain['adresse'] . ', ' . $terrain['ville']); ?></p>
                <div>
                    <span class="terrain-badge"><?php echo htmlspecialchars($terrain['taille']); ?></span>
                    <span class="terrain-badge"><?php echo htmlspecialchars($terrain['type']); ?></span>
                    <span class="terrain-badge"><?php echo number_format($terrain['prix_heure'], 2); ?> DH/h</span>
                </div>
            </div>

            <!-- Informations utilisateur  -->
            <h3 class="section-title">
                <i class="fas fa-user"></i>
                Vos informations
            </h3>
            <div class="user-info">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Nom complet</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Téléphone</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['telephone'] ?? 'Non renseigné'); ?></span>
                    </div>
                </div>
            </div>

            <form method="POST" id="reservationForm">
                <!-- Date de reservation -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-calendar"></i>
                        Date de réservation
                    </label>
                    <input type="date" name="date_reservation" id="dateReservation" class="form-input" 
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <!-- les creanaux -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-clock"></i>
                        Créneaux horaires disponibles
                    </label>
                    <div id="timeSlotsContainer">
                        <p style="color: #6b7280; text-align: center; padding: 2rem;">
                            Veuillez sélectionner une date pour voir les créneaux disponibles
                        </p>
                    </div>
                </div>

                <!-- Opt.supp -->
                <?php if (!empty($options)): ?>
                <div class="form-group">
                    <h3 class="section-title">
                        <i class="fas fa-plus-circle"></i>
                        Options supplémentaires
                    </h3>
                    <div class="options-list">
                        <?php foreach ($options as $option): ?>
                            <div class="option-item" onclick="toggleOption(this, <?php echo $option['id_option']; ?>, <?php echo $option['prix']; ?>)">
                                <div class="option-checkbox">
                                    <input type="checkbox" 
                                           name="options[]" 
                                           value="<?php echo $option['id_option']; ?>"
                                           id="option_<?php echo $option['id_option']; ?>"
                                           data-price="<?php echo $option['prix']; ?>">
                                    <span class="option-name"><?php echo htmlspecialchars($option['nom_option']); ?></span>
                                </div>
                                <span class="option-price"><?php echo number_format($option['prix'], 2); ?> DH</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Commentaires -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-comment"></i>
                        Commentaires (optionnel)
                    </label>
                    <textarea name="commentaires" class="form-textarea" rows="4" 
                              placeholder="Ajoutez des remarques ou demandes spécifiques..."></textarea>
                </div>
            </form>
        </div>

        <!-- Sidebar - Panier -->
        <div class="sidebar">
            <div class="cart">
                <h3 class="cart-title">
                    <i class="fas fa-shopping-cart"></i>
                    Récapitulatif
                </h3>

                <div id="cartItems">
                    <div class="cart-item">
                        <span class="cart-item-name">Terrain</span>
                        <span class="cart-item-price" id="terrainPrice"><?php echo number_format($terrain['prix_heure'], 2); ?> DH</span>
                    </div>
                    <div id="optionsCart"></div>
                </div>

                <div class="cart-total">
                    <div class="cart-total-row">
                        <span class="cart-total-label">Total</span>
                        <span class="cart-total-value" id="totalPrice"><?php echo number_format($terrain['prix_heure'], 2); ?> DH</span>
                    </div>
                </div>

                <button type="submit" form="reservationForm" class="btn-submit" id="submitBtn" disabled>
                    <i class="fas fa-calendar-check"></i>
                    Confirmer la réservation
                </button>
            </div>
        </div>
    </div>

    <script>
        const terrainPrice = <?php echo $terrain['prix_heure']; ?>;
        const terrainId = <?php echo $terrain['id_terrain']; ?>;
        let selectedOptions = {};
        let selectedTimeSlot = null;

        // mn 8 l 22
        const timeSlots = [];
        for (let hour = 8; hour < 22; hour++) {
            timeSlots.push(`${hour.toString().padStart(2, '0')}:00:00`);
        }

        // Charger les heures
        document.getElementById('dateReservation').addEventListener('change', function() {
            const date = this.value;
            if (date) {
                loadAvailableSlots(date);
            }
        });

        function loadAvailableSlots(date) {
            fetch(`get_available_slots.php?terrain_id=${terrainId}&date=${date}`)
                .then(response => response.json())
                .then(data => {
                    displayTimeSlots(data.booked_slots);
                    document.getElementById('submitBtn').disabled = true;
                    selectedTimeSlot = null;
                })
                .catch(error => {
                    console.error('Erreur:', error);
                });
        }

        function displayTimeSlots(bookedSlots) {
            const container = document.getElementById('timeSlotsContainer');
            const availableSlots = timeSlots.filter(slot => !bookedSlots.includes(slot));

            if (availableSlots.length === 0) {
                container.innerHTML = `
                    <div class="no-slots-message">
                        <i class="fas fa-info-circle"></i>
                        <p>Aucun créneau disponible pour cette date. Veuillez choisir une autre date.</p>
                    </div>
                `;
                return;
            }

            let html = '<div class="time-slots">';
            
            timeSlots.forEach(slot => {
                const isBooked = bookedSlots.includes(slot);
                const [hour] = slot.split(':');
                const displayTime = `${hour}h-${parseInt(hour) + 1}h`;
                
                html += `
                    <div class="time-slot ${isBooked ? 'disabled' : ''}">
                        <input type="radio" 
                               name="heure_debut" 
                               value="${slot}" 
                               id="slot_${slot}" 
                               ${isBooked ? 'disabled' : ''}
                               onchange="selectTimeSlot()">
                        <label for="slot_${slot}">${displayTime}</label>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }

        function selectTimeSlot() {
            selectedTimeSlot = document.querySelector('input[name="heure_debut"]:checked');
            updateSubmitButton();
        }

        function toggleOption(element, optionId, price) {
            const checkbox = element.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                selectedOptions[optionId] = price;
                element.classList.add('selected');
            } else {
                delete selectedOptions[optionId];
                element.classList.remove('selected');
            }
            
            updateCart();
        }

        function updateCart() {
            const optionsCart = document.getElementById('optionsCart');
            let html = '';
            let optionsTotal = 0;

            for (const [optionId, price] of Object.entries(selectedOptions)) {
                const checkbox = document.getElementById(`option_${optionId}`);
                const optionName = checkbox.parentElement.querySelector('.option-name').textContent;
                optionsTotal += parseFloat(price);
                
                html += `
                    <div class="cart-item">
                        <span class="cart-item-name">${optionName}</span>
                        <span class="cart-item-price">${parseFloat(price).toFixed(2)} DH</span>
                    </div>
                `;
            }

            optionsCart.innerHTML = html;

            const total = terrainPrice + optionsTotal;
            document.getElementById('totalPrice').textContent = total.toFixed(2) + ' DH';
        }

        function updateSubmitButton() {
            const submitBtn = document.getElementById('submitBtn');
            const dateSelected = document.getElementById('dateReservation').value;
            
            if (dateSelected && selectedTimeSlot) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }

        // validation
        document.getElementById('reservationForm').addEventListener('submit', function(e) {
            if (!selectedTimeSlot) {
                e.preventDefault();
                alert('Veuillez sélectionner un créneau horaire');
            }
        });
    </script>
    <script>
    const terrainPrice = <?php echo $terrain['prix_heure']; ?>;
    const terrainId = <?php echo $terrain['id_terrain']; ?>;
   

    // generer les creanaux
    
    for (let hour = 8; hour < 22; hour++) {
        timeSlots.push(`${hour.toString().padStart(2, '0')}:00:00`);
    }

    // Charger les crenaux dispo
    document.getElementById('dateReservation').addEventListener('change', function() {
        const date = this.value;
        if (date) {
            loadAvailableSlots(date);
        }
    });

    function loadAvailableSlots(date) {
        
        const bookedSlots = []; 
        displayTimeSlots(bookedSlots);
        
    
    }

    function displayTimeSlots(bookedSlots) {
        const container = document.getElementById('timeSlotsContainer');
        const availableSlots = timeSlots.filter(slot => !bookedSlots.includes(slot));

        if (availableSlots.length === 0) {
            container.innerHTML = `
                <div class="no-slots-message">
                    <i class="fas fa-info-circle"></i>
                    <p>Aucun créneau disponible pour cette date. Veuillez choisir une autre date.</p>
                </div>
            `;
            return;
        }

        let html = '<div class="time-slots">';
        
        timeSlots.forEach(slot => {
            const isBooked = bookedSlots.includes(slot);
            const [hour] = slot.split(':');
            const displayTime = `${hour}h-${parseInt(hour) + 1}h`;
            
            html += `
                <div class="time-slot ${isBooked ? 'disabled' : ''}">
                    <input type="radio" 
                           name="heure_debut" 
                           value="${slot}" 
                           id="slot_${slot}" 
                           ${isBooked ? 'disabled' : ''}
                           onchange="selectTimeSlot(this)">
                    <label for="slot_${slot}">${displayTime}</label>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
    }

    function selectTimeSlot(radio) {
        selectedTimeSlot = radio;
        updateSubmitButton();
    }

    function toggleOption(element, optionId, price) {
        const checkbox = element.querySelector('input[type="checkbox"]');
        
        // les cases a cocher
        checkbox.checked = !checkbox.checked;
        
        if (checkbox.checked) {
            selectedOptions[optionId] = {
                price: price,
                name: element.querySelector('.option-name').textContent
            };
            element.classList.add('selected');
        } else {
            delete selectedOptions[optionId];
            element.classList.remove('selected');
        }
        
        updateCart();
        updateSubmitButton();
    }

    function updateCart() {
        const optionsCart = document.getElementById('optionsCart');
        let html = '';
        let optionsTotal = 0;

        // update panier
        for (const [optionId, option] of Object.entries(selectedOptions)) {
            optionsTotal += parseFloat(option.price);
            
            html += `
                <div class="cart-item">
                    <span class="cart-item-name">${option.name}</span>
                    <span class="cart-item-price">${parseFloat(option.price).toFixed(2)} DH</span>
                </div>
            `;
        }

        optionsCart.innerHTML = html;

        //maj la somme
        const total = terrainPrice + optionsTotal;
        document.getElementById('totalPrice').textContent = total.toFixed(2) + ' DH';
    }

    function updateSubmitButton() {
        const submitBtn = document.getElementById('submitBtn');
        const dateSelected = document.getElementById('dateReservation').value;
        
        // activer button
        submitBtn.disabled = !(dateSelected && selectedTimeSlot);
    }

    // Validation
    document.getElementById('reservationForm').addEventListener('submit', function(e) {
        const dateSelected = document.getElementById('dateReservation').value;
        
        if (!dateSelected) {
            e.preventDefault();
            alert('Veuillez sélectionner une date');
            return;
        }
        
        if (!selectedTimeSlot) {
            e.preventDefault();
            alert('Veuillez sélectionner un créneau horaire');
            return;
        }
        
        //subnmit
        console.log('Formulaire soumis avec succès');
    });

    // initialiser le panier et button
    document.addEventListener('DOMContentLoaded', function() {
        updateCart();
        updateSubmitButton();
    });
</script>
</body>
</html>