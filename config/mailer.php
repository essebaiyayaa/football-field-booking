<?php

require_once __DIR__ . '/config.php';

// Autoload 
require_once __DIR__ . '/../vendor/autoload.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->configure();
    }
    
private function configure() {
    try {
      
        $this->mail->isSMTP();
        $this->mail->Host = env('SMTP_HOST', 'smtp.gmail.com');
        $this->mail->SMTPAuth = filter_var(env('SMTP_AUTH', true), FILTER_VALIDATE_BOOLEAN);
        $this->mail->Username = env('SMTP_USERNAME');
        $this->mail->Password = env('SMTP_PASSWORD');
        $this->mail->SMTPSecure = env('SMTP_ENCRYPTION', 'tls');
        $this->mail->Port = env('SMTP_PORT', 587);
        
        
        $this->mail->CharSet = 'UTF-8';
        $this->mail->Encoding = 'base64';
        
       
        $this->mail->setFrom(env('MAIL_FROM'), env('MAIL_FROM_NAME', SITE_NAME));
        $this->mail->addReplyTo(env('MAIL_FROM'), env('MAIL_FROM_NAME', SITE_NAME));
        
      
        $this->mail->SMTPDebug = 0;
        $this->mail->Debugoutput = 'error_log';
        
    } catch (Exception $e) {
        error_log("Erreur configuration PHPMailer: " . $e->getMessage());
        throw $e; 
    }
}
    
    public function sendVerificationEmail($to, $name, $verification_link) {
        try {
           
            $this->mail->clearAddresses();
            $this->mail->addAddress($to, $name);
            
            $this->mail->isHTML(true);
            $this->mail->Subject = "Vérification de votre compte " . SITE_NAME;
            $this->mail->Body = $this->getVerificationEmailTemplate($name, $verification_link);
            $this->mail->AltBody = $this->getVerificationTextTemplate($name, $verification_link);
            
            
            $this->mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Erreur PHPMailer: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    public function sendWelcomeEmail($to, $name) {
        try {
           
            $this->mail->clearAddresses();
            $this->mail->addAddress($to, $name);
            
            $this->mail->isHTML(true);
            $this->mail->Subject = "Bienvenue sur " . SITE_NAME . " !";
            $this->mail->Body = $this->getWelcomeEmailTemplate($name);
            $this->mail->AltBody = $this->getWelcomeTextTemplate($name);
            
          
            $this->mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Erreur PHPMailer: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    private function getVerificationEmailTemplate($name, $verification_link) {
        $expiry_hours = ceil(env('VERIFICATION_TOKEN_EXPIRY', 86400) / 3600);
        
        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Vérification d'email</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f9fafb;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: white;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                }
                .header { 
                    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); 
                    color: white; 
                    padding: 40px 30px; 
                    text-align: center; 
                }
                .content { 
                    padding: 40px 30px; 
                }
                .button { 
                    display: inline-block; 
                    background: #16a34a; 
                    color: white; 
                    padding: 14px 35px; 
                    text-decoration: none; 
                    border-radius: 8px; 
                    margin: 25px 0; 
                    font-weight: bold;
                    font-size: 16px;
                }
                .footer { 
                    text-align: center; 
                    margin-top: 30px; 
                    color: #6b7280; 
                    font-size: 14px;
                    padding: 20px;
                    background: #f9fafb;
                }
                .verification-link {
                    word-break: break-all;
                    background: #f3f4f6;
                    padding: 15px;
                    border-radius: 6px;
                    margin: 20px 0;
                    color: #374151;
                    font-size: 14px;
                }
                .expiry-notice {
                    background: #fef3c7;
                    padding: 12px;
                    border-radius: 6px;
                    margin: 15px 0;
                    color: #92400e;
                    font-size: 14px;
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Bienvenue sur " . SITE_NAME . " !</h1>
                    <p>Activez votre compte pour commencer</p>
                </div>
                <div class='content'>
                    <p>Bonjour <strong>$name</strong>,</p>
                    <p>Merci de vous être inscrit sur " . SITE_NAME . " ! Pour activer votre compte et profiter de toutes nos fonctionnalités, veuillez cliquer sur le bouton ci-dessous :</p>
                    
                    <div style='text-align: center;'>
                        <a href='$verification_link' class='button'>Vérifier mon email</a>
                    </div>
                    
                    <div class='expiry-notice'>
                         Ce lien est valable pendant $expiry_hours heures
                    </div>
                    
                    <p>Si le bouton ne fonctionne pas, copiez et collez le lien suivant dans votre navigateur :</p>
                    <div class='verification-link'>$verification_link</div>
                    
                    <p>Si vous n'avez pas créé de compte sur " . SITE_NAME . ", vous pouvez ignorer cet email en toute sécurité.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " " . SITE_NAME . " - Tous droits réservés</p>
                    <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getVerificationTextTemplate($name, $verification_link) {
        $expiry_hours = ceil(env('VERIFICATION_TOKEN_EXPIRY', 86400) / 3600);
        
        return "
        Vérification de votre compte " . SITE_NAME . "
        
        Bonjour $name,
        
        Merci de vous être inscrit sur " . SITE_NAME . " ! 
        Pour activer votre compte, veuillez cliquer sur le lien suivant :
        
        $verification_link
        
        Ce lien est valable pendant $expiry_hours heures.
        
        Si vous n'avez pas créé de compte, ignorez cet email.
        
        Cordialement,
        L'équipe " . SITE_NAME . "
        ";
    }
    
    private function getWelcomeEmailTemplate($name) {
        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Bienvenue</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f9fafb;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: white;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                }
                .header { 
                    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); 
                    color: white; 
                    padding: 40px 30px; 
                    text-align: center; 
                }
                .content { 
                    padding: 40px 30px; 
                }
                .button { 
                    display: inline-block; 
                    background: #16a34a; 
                    color: white; 
                    padding: 14px 35px; 
                    text-decoration: none; 
                    border-radius: 8px; 
                    margin: 25px 0; 
                    font-weight: bold;
                    font-size: 16px;
                }
                .footer { 
                    text-align: center; 
                    margin-top: 30px; 
                    color: #6b7280; 
                    font-size: 14px;
                    padding: 20px;
                    background: #f9fafb;
                }
                .feature-list {
                    margin: 20px 0;
                }
                .feature-item {
                    padding: 10px 0;
                    display: flex;
                    align-items: center;
                }
                .feature-item i {
                    color: #16a34a;
                    margin-right: 10px;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1> Bienvenue sur " . SITE_NAME . " !</h1>
                    <p>Votre compte a été activé avec succès</p>
                </div>
                <div class='content'>
                    <p>Bonjour <strong>$name</strong>,</p>
                    <p>Félicitations ! Votre compte a été activé avec succès. Vous pouvez maintenant profiter de tous nos services :</p>
                    
                    <div class='feature-list'>
                        <div class='feature-item'> Réserver des terrains en quelques clics</div>
                        <div class='feature-item'> Gérer vos réservations facilement</div>
                        <div class='feature-item'> Accéder aux promotions exclusives</div>
                        <div class='feature-item'> Participer à des tournois et événements</div>
                        <div class='feature-item'> Consulter votre historique de réservations</div>
                    </div>
                    
                    <div style='text-align: center;'>
                        <a href='" . SITE_URL . "/auth/login.php' class='button'>Commencer à réserver</a>
                    </div>
                    
                    <p>Si vous avez des questions ou besoin d'aide, n'hésitez pas à nous contacter.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " " . SITE_NAME . " - Tous droits réservés</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getWelcomeTextTemplate($name) {
        return "
        Bienvenue sur " . SITE_NAME . " !
        
        Bonjour $name,
        
        Félicitations ! Votre compte a été activé avec succès.
        
        Vous pouvez maintenant :
        - Réserver des terrains en quelques clics
        - Gérer vos réservations facilement
        - Accéder aux promotions exclusives
        - Participer à des tournois et événements
        
        Commencez dès maintenant : " . SITE_URL . "/auth/login.php
        
        Cordialement,
        L'équipe " . SITE_NAME . "
        ";
    }
}
?>