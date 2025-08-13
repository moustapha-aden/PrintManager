<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config; // Importez la façade Config

class CustomResetPasswordNotification extends ResetPasswordNotification
{
    /**
     * Get the password reset notification mail message.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        // Récupérer l'URL de votre frontend depuis la configuration ou .env
        // Assurez-vous que FRONTEND_URL est défini dans votre fichier .env
        $frontendUrl = Config::get('app.frontend_url') ?: env('FRONTEND_URL');

        // Construire l'URL complète vers votre page de réinitialisation React
        // Exemple: http://localhost:3000/reset-password?token=XYZ&email=abc@example.com
        $url = $frontendUrl . 'http:/http://87.106.107.227/reset-password?token=' . $this->token . '&email=' . $notifiable->getEmailForPasswordReset();

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe')
            ->line('Vous recevez cet e-mail car nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.')
            ->action('Réinitialiser le mot de passe', $url) // Le bouton pointera vers l'URL de votre frontend
            ->line('Ce lien de réinitialisation de mot de passe expirera dans ' . Config::get('auth.passwords.users.expire') . ' minutes.')
            ->line('Si vous n\'avez pas demandé de réinitialisation de mot de passe, aucune action n\'est requise.');
    }
}
