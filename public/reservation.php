
<?php

require_once '../config/database.php';
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    
    $date = $_POST['date']; 
    $type = $_POST['type']; 
    $creneau = $_POST['creneau']; 
    $taille = $_POST['taille']; 
    $options_sup = $_POST["options"] ?? []; 
    $demande = $_POST['demande']; 
    $nom_client = $_POST['nom']; 
    $prenom_client = $_POST['prenom']; 
    $email_client = $_POST['email']; 
    $tel_client = $_POST['telephone'];
    $id_terrain = $_POST['id_terrain'] ?? 1;
    $commentaires = $_POST['commentaires'] ?? "Some text";

    
    if (preg_match('/^(\d{1,2})h-(\d{1,2})h$/', $creneau, $matches)) {
        $heure_debut = str_pad($matches[1], 2, '0', STR_PAD_LEFT) . ":00:00";
        $heure_fin = str_pad($matches[2], 2, '0', STR_PAD_LEFT) . ":00:00";
    } else {
        die("Format du créneau invalide. Exemple attendu : 16h-17h");
    }

    // Prices
    $prix_options = [
        'ballons' => 50,
        'maillots' => 100,
        'arbitre' => 200,
        'douche' => 30
    ];

    try {
        $pdo->beginTransaction();

        
        $stmt_user = $pdo->prepare("SELECT id_utilisateur FROM Utilisateur WHERE email = :email");
        $stmt_user->execute([':email' => $email_client]);
        $id_utilisateur = $stmt_user->fetchColumn();

        
        if (!$id_utilisateur) {
            header('Location: /register.php');
            exit;
        }

        
        $stmt_res = $pdo->prepare("
            INSERT INTO Reservation (date_reservation, heure_debut, heure_fin, id_utilisateur, id_terrain, commentaires)
            VALUES (:date_reservation, :heure_debut, :heure_fin, :id_utilisateur, :id_terrain, :commentaires)
        ");
        $stmt_res->execute([
            ':date_reservation' => $date,
            ':heure_debut' => $heure_debut,
            ':heure_fin' => $heure_fin,
            ':id_utilisateur' => $id_utilisateur,
            ':id_terrain' => $id_terrain,
            ':commentaires' => $commentaires
        ]);

        $id_reservation = $pdo->lastInsertId();

        $stmt_check = $pdo->prepare("SELECT id_option FROM OptionSupplementaire WHERE nom_option = :nom");
        $stmt_insert_option = $pdo->prepare("
            INSERT INTO OptionSupplementaire (nom_option, prix) VALUES (:nom, :prix)
        ");
        $stmt_link = $pdo->prepare("
            INSERT INTO Reservation_Option (id_reservation, id_option)
            VALUES (:id_reservation, :id_option)
        ");

        foreach ($options_sup as $nom_option) {
            $nom_option = strtolower(trim($nom_option)); 

            
            $stmt_check->execute([':nom' => $nom_option]);
            $id_option = $stmt_check->fetchColumn();

            if (!$id_option) {
                $stmt_insert_option->execute([
                    ':nom' => $nom_option,
                    ':prix' => $prix_options[$nom_option] ?? 0
                ]);
                $id_option = $pdo->lastInsertId();
            }

            
            $stmt_link->execute([
                ':id_reservation' => $id_reservation,
                ':id_option' => $id_option
            ]);
        }

        $pdo->commit();
        echo "Réservation et options enregistrées avec succès!";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Erreur : " . $e->getMessage();
    }
}
?>





<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulaire de Réservation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/lucide-static@0.263.0/umd/lucide.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f0fdf4 0%, #d1fae5 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            color: #059669;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .header p {
            color: #6b7280;
            font-size: 1.1rem;
        }

        .form-container {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            padding: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (min-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: flex;
            align-items: center;
            color: #374151;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .form-label i {
            color: #059669;
            margin-right: 0.5rem;
            width: 1.25rem;
            text-align: center;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 2px solid #e5e7eb;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #059669;
        }

        .form-input.error, .form-select.error {
            border-color: #ef4444;
        }

        .error-message {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .options-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        @media (min-width: 768px) {
            .options-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .option-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: border-color 0.3s;
        }

        .option-item:hover {
            border-color: #059669;
        }

        .option-checkbox {
            display: flex;
            align-items: center;
        }

        .option-checkbox input {
            width: 1.25rem;
            height: 1.25rem;
            color: #059669;
            border-radius: 0.25rem;
            margin-right: 0.75rem;
        }

        .option-price {
            color: #059669;
            font-weight: 600;
        }

        .total-options {
            margin-top: 1rem;
            padding: 1rem;
            background-color: #ecfdf5;
            border-radius: 0.5rem;
        }

        .total-options p {
            color: #065f46;
            font-weight: 700;
            font-size: 1.125rem;
        }

        .section-title {
            display: flex;
            align-items: center;
            color: #374151;
            font-weight: 700;
            font-size: 1.125rem;
            margin-bottom: 1rem;
        }

        .section-title i {
            color: #059669;
            margin-right: 0.5rem;
        }

        .submit-btn {
            width: 100%;
            background-color: #059669;
            color: white;
            font-weight: 700;
            padding: 1rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .submit-btn:hover {
            background-color: #047857;
            transform: scale(1.02);
        }

        .success-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #f0fdf4 0%, #d1fae5 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .success-card {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            padding: 2rem;
            max-width: 28rem;
            width: 100%;
            text-align: center;
        }

        .success-icon {
            width: 5rem;
            height: 5rem;
            background-color: #dcfce7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .success-icon i {
            color: #059669;
            font-size: 2.5rem;
        }

        .success-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .success-message {
            color: #6b7280;
            margin-bottom: 1rem;
        }

        .success-detail {
            font-size: 0.875rem;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div id="app">
        <!-- Success message (hidden by default) -->
        <div id="success-container" class="success-container" style="display: none;">
            <div class="success-card">
                <div class="success-icon">
                    <i class="fas fa-check-square"></i>
                </div>
                <h2 class="success-title">Réservation confirmée !</h2>
                <p class="success-message" id="success-name">Merci [Prénom] [Nom]</p>
                <p class="success-detail" id="success-email">Un email de confirmation a été envoyé à [email]</p>
            </div>
        </div>

        <!-- Main form -->
        <div id="main-form">
            <div class="container">
                <div class="header">
                    <h1>Formulaire de Réservation</h1>
                    <p>Remplissez les informations ci-dessous pour réserver votre terrain.</p>
                </div>

                <form id="reservation-form" class="form-container" method="POST" action="">
                    <div class="form-grid">
                        <!-- Date de réservation -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-calendar"></i>
                                Date de réservation
                            </label>
                            <input type="date" id="date" name="date" class="form-input">
                            <p id="date-error" class="error-message" style="display: none;">Date requise</p>
                        </div>

                        <!-- Type de terrain -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-users"></i>
                                Type de terrain
                            </label>
                            <select id="type" name="type" class="form-select">
                                <option value="">Sélectionnez le type</option>
                                <option value="Gazon naturel">Gazon naturel</option>
                                <option value="Gazon artificiel">Gazon artificiel</option>
                                <option value="Terrain dur">Terrain dur</option>
                            </select>
                            <p id="type-error" class="error-message" style="display: none;">Type requis</p>
                        </div>

                        <!-- Créneau horaire -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-clock"></i>
                                Créneau horaire
                            </label>
                            <select id="creneau" name="creneau" class="form-select">
                                <option value="">Sélectionnez un créneau</option>
                                <option value="16h-17h">16h-17h</option>
                                <option value="17h-18h">17h-18h</option>
                                <option value="18h-19h">18h-19h</option>
                                <option value="19h-20h">19h-20h</option>
                                <option value="20h-21h">20h-21h</option>
                            </select>
                            <p id="creneau-error" class="error-message" style="display: none;">Créneau requis</p>
                        </div>

                        <!-- Taille du terrain -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-expand-alt"></i>
                                Taille du terrain
                            </label>
                            <select id="taille" name="taille" class="form-select">
                                <option value="">Sélectionnez la taille</option>
                                <option value="Mini foot">Mini foot</option>
                                <option value="Terrain moyen">Terrain moyen</option>
                                <option value="Grand terrain">Grand terrain</option>
                            </select>
                            <p id="taille-error" class="error-message" style="display: none;">Taille requise</p>
                        </div>
                    </div>

                    <!-- Options supplémentaires -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-check-square"></i>
                            Options supplémentaires
                        </label>
                        <div class="options-grid">
                            <div class="option-item" data-option="ballon">
                                <div class="option-checkbox">
                                    <input type="checkbox" id="ballon" name="options[]" value="ballon">
                                    <span>Ballon</span>
                                </div>
                                <span class="option-price">50 MAD</span>
                            </div>
                            <div class="option-item" data-option="arbitre">
                                <div class="option-checkbox">
                                    <input type="checkbox" id="arbitre" name="options[]" value="arbitre">
                                    <span>Arbitre</span>
                                </div>
                                <span class="option-price">200 MAD</span>
                            </div>
                            <div class="option-item" data-option="maillots">
                                <div class="option-checkbox">
                                    <input type="checkbox" id="maillots" name="options[]" value="maillots">
                                    <span>Maillots</span>
                                </div>
                                <span class="option-price">100 MAD</span>
                            </div>
                            <div class="option-item" data-option="douche">
                                <div class="option-checkbox">
                                    <input type="checkbox" id="douche" name="options[]" value="douche">
                                    <span>Douche</span>
                                </div>
                                <span class="option-price">30 MAD</span>
                            </div>
                        </div>
                        <div id="total-options" class="total-options" style="display: none;">
                            <p>Total options: <span id="total-price">0</span> MAD</p>
                        </div>
                    </div>

                    <!-- Demande spécifique -->
                    <div class="form-group">
                        <label class="form-label">Demande spécifique</label>
                        <textarea id="demande" name="demande" class="form-textarea" rows="4" placeholder="Écrivez ici vos remarques ou demandes particulières..."></textarea>
                    </div>

                    <!-- Informations du client -->
                    <div class="form-group">
                        <h3 class="section-title">
                            <i class="fas fa-user"></i>
                            Informations du client
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Nom</label>
                                <input type="text" id="nom" name="nom" class="form-input" placeholder="Dupont">
                                <p id="nom-error" class="error-message" style="display: none;">Nom requis</p>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Prénom</label>
                                <input type="text" id="prenom" name="prenom" class="form-input" placeholder="Jean">
                                <p id="prenom-error" class="error-message" style="display: none;">Prénom requis</p>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-envelope"></i>
                                    Email
                                </label>
                                <input type="email" id="email" name="email" class="form-input" placeholder="jean.dupont@email.com">
                                <p id="email-error" class="error-message" style="display: none;">Email requis</p>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-phone"></i>
                                    Téléphone
                                </label>
                                <input type="tel" id="telephone" name="telephone" class="form-input" placeholder="06 12 34 56 78">
                                <p id="telephone-error" class="error-message" style="display: none;">Téléphone requis</p>
                            </div>
                        </div>
                    </div>

                    <!-- Bouton de soumission -->
                    <button type="submit" class="submit-btn">Confirmer la réservation</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Variables pour stocker les données du formulaire
            const formData = {
                date: '',
                creneau: '',
                taille: '',
                type: '',
                options: {
                    ballon: false,
                    arbitre: false,
                    maillots: false,
                    douche: false
                },
                demande: '',
                nom: '',
                prenom: '',
                email: '',
                telephone: ''
            };

            // Prix des options
            const optionsPrix = {
                ballon: 50,
                arbitre: 200,
                maillots: 100,
                douche: 30
            };

            // Éléments du DOM
            const mainForm = document.getElementById('main-form');
            const successContainer = document.getElementById('success-container');
            const reservationForm = document.getElementById('reservation-form');
            const totalOptions = document.getElementById('total-options');
            const totalPrice = document.getElementById('total-price');
            const successName = document.getElementById('success-name');
            const successEmail = document.getElementById('success-email');

            // Gestion des changements d'input
            document.querySelectorAll('input, select, textarea').forEach(element => {
                element.addEventListener('input', function() {
                    const name = this.name || this.id;
                    const value = this.type === 'checkbox' ? this.checked : this.value;
                    
                    if (name in formData.options) {
                        formData.options[name] = value;
                        updateTotal();
                    } else {
                        formData[name] = value;
                    }
                    
                    // Effacer l'erreur si le champ est rempli
                    if (value) {
                        hideError(name);
                    }
                });
            });

            // Gestion des clics sur les options
            document.querySelectorAll('.option-item').forEach(item => {
                item.addEventListener('click', function() {
                    const option = this.getAttribute('data-option');
                    const checkbox = document.getElementById(option);
                    checkbox.checked = !checkbox.checked;
                    formData.options[option] = checkbox.checked;
                    updateTotal();
                });
            });

            // Mise à jour du total des options
            function updateTotal() {
                let total = 0;
                for (const option in formData.options) {
                    if (formData.options[option]) {
                        total += optionsPrix[option];
                    }
                }
                
                if (total > 0) {
                    totalPrice.textContent = total;
                    totalOptions.style.display = 'block';
                } else {
                    totalOptions.style.display = 'none';
                }
            }

            // Validation du formulaire
            function validateForm() {
                let isValid = true;
                
                // Réinitialiser les erreurs
                document.querySelectorAll('.error-message').forEach(error => {
                    error.style.display = 'none';
                });
                document.querySelectorAll('.form-input, .form-select').forEach(input => {
                    input.classList.remove('error');
                });
                
                // Validation des champs requis
                if (!formData.date) {
                    showError('date', 'Date requise');
                    isValid = false;
                }
                
                if (!formData.creneau) {
                    showError('creneau', 'Créneau requis');
                    isValid = false;
                }
                
                if (!formData.taille) {
                    showError('taille', 'Taille requise');
                    isValid = false;
                }
                
                if (!formData.type) {
                    showError('type', 'Type requis');
                    isValid = false;
                }
                
                if (!formData.nom) {
                    showError('nom', 'Nom requis');
                    isValid = false;
                }
                
                if (!formData.prenom) {
                    showError('prenom', 'Prénom requis');
                    isValid = false;
                }
                
                if (!formData.email) {
                    showError('email', 'Email requis');
                    isValid = false;
                } else if (!/\S+@\S+\.\S+/.test(formData.email)) {
                    showError('email', 'Email invalide');
                    isValid = false;
                }
                
                if (!formData.telephone) {
                    showError('telephone', 'Téléphone requis');
                    isValid = false;
                }
                
                return isValid;
            }

            // Afficher une erreur
            function showError(field, message) {
                const errorElement = document.getElementById(field + '-error');
                const inputElement = document.getElementById(field);
                
                if (errorElement) {
                    errorElement.textContent = message;
                    errorElement.style.display = 'block';
                }
                
                if (inputElement) {
                    inputElement.classList.add('error');
                }
            }

            // Cacher une erreur
            function hideError(field) {
                const errorElement = document.getElementById(field + '-error');
                const inputElement = document.getElementById(field);
                
                if (errorElement) {
                    errorElement.style.display = 'none';
                }
                
                if (inputElement) {
                    inputElement.classList.remove('error');
                }
            }

            // // Soumission du formulaire
            // reservationForm.addEventListener('submit', function(e) {
            //     e.preventDefault();
                
            //     if (validateForm()) {
            //         // Afficher le message de succès
            //         successName.textContent = Merci ${formData.prenom} ${formData.nom};
            //         successEmail.textContent = Un email de confirmation a été envoyé à ${formData.email};
                    
            //         mainForm.style.display = 'none';
            //         successContainer.style.display = 'flex';
                    
            //         // Réinitialiser le formulaire après 3 secondes
            //         setTimeout(() => {
            //             successContainer.style.display = 'none';
            //             mainForm.style.display = 'block';
            //             reservationForm.reset();
                        
            //             // Réinitialiser formData
            //             for (const key in formData) {
            //                 if (key === 'options') {
            //                     for (const option in formData.options) {
            //                         formData.options[option] = false;
            //                     }
            //                 } else {
            //                     formData[key] = '';
            //                 }
            //             }
                        
            //             // Cacher le total des options
            //             totalOptions.style.display = 'none';
            //         }, 3000);
            //     }
            // });
        });
    </script>
</body>
</html>