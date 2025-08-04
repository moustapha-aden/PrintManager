<?php

namespace App\Mail;

use App\Models\Intervention; // Assurez-vous que le chemin vers votre modèle Intervention est correct
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InterventionStatusUpdateNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $intervention; // L'objet intervention
    public $oldStatus;    // L'ancien statut de l'intervention

    /**
     * Create a new message instance.
     */
    public function __construct(Intervention $intervention, $oldStatus = null)
    {
        $this->intervention = $intervention;
        $this->oldStatus = $oldStatus; // Utile pour personnaliser le message si besoin
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Personnalisez le sujet en fonction du statut ou de l'action
        $subject = 'Mise à jour de votre demande d\'intervention #' . ($this->intervention->numero_demande ?? $this->intervention->id);

        // Vous pouvez affiner le sujet si le statut est "Terminée" ou "Assignée"
        if ($this->intervention->status === 'Terminée') {
            $subject = 'Votre intervention est terminée : #' . ($this->intervention->numero_demande ?? $this->intervention->id);
        } elseif ($this->intervention->status === 'En Cours' && $this->oldStatus !== 'En Cours') { // Vérifie si le statut est passé à "En Cours"
             $subject = 'Votre intervention est désormais en cours : #' . ($this->intervention->numero_demande ?? $this->intervention->id);
        } elseif ($this->intervention->status === 'Assignée' && $this->oldStatus !== 'Assignée') { // Si vous avez un statut "Assignée" distinct
            $subject = 'Un technicien a été assigné à votre demande : #' . ($this->intervention->numero_demande ?? $this->intervention->id);
        }

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.intervention_status_update', // Nous allons créer cette vue Blade
            with: [
                'intervention' => $this->intervention,
                'oldStatus' => $this->oldStatus,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
