<?php

namespace App\Mail;

use App\Models\Intervention; // Assurez-vous que le chemin vers votre modèle Intervention est correct
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // Gardez ceci si vous envisagez les queues plus tard
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewInterventionNotification extends Mailable // Vous pouvez ajouter "implements ShouldQueue" ici si vous voulez utiliser les files d'attente
{
    use Queueable, SerializesModels;

    public $intervention; // Permet d'accéder aux données de l'intervention dans la vue Blade

    /**
     * Create a new message instance.
     */
    public function __construct(Intervention $intervention)
    {
        $this->intervention = $intervention;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nouvelle Demande d\'Intervention Créée - ' . ($this->intervention->numero_demande ?? 'N/A'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.new_intervention', // Indique à Laravel d'utiliser cette vue Blade pour le contenu HTML de l'e-mail
            with: ['intervention' => $this->intervention], // Passe l'objet intervention à la vue
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return []; // Vous pouvez ajouter des pièces jointes ici si nécessaire
    }
}
