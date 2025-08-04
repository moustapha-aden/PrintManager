<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewInterventionNotification;
use App\Mail\InterventionStatusUpdateNotification; // Nouvelle classe Mailable pour le client
use App\Models\Intervention;
use App\Models\User; // Assurez-vous que le modèle User est importé pour la création de test
use App\Models\Printer; // Assurez-vous que le modèle Printer est importé pour la création de test
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Route de test existante pour l'envoi d'e-mail aux administrateurs
Route::get('/test-admin-email', function () {
    try {
        // Créez une fausse intervention pour le test, ou récupérez-en une existante
        $intervention = Intervention::first();
        if (!$intervention) {
            $client = User::where('role', 'client')->first() ?? User::factory()->create(['role' => 'client', 'email' => 'client_test@example.com']);
            $printer = Printer::first() ?? Printer::factory()->create();
            $intervention = Intervention::create([
                'numero_demande' => 'TEST-' . time(),
                'start_date' => now(),
                'client_id' => $client->id,
                'printer_id' => $printer->id,
                'status' => 'En Attente',
                'description' => 'Test email description for admin',
                'priority' => 'Basse',
                'intervention_type' => 'Logiciel',
            ]);
        }

        // Remplacez 'admin_test@example.com' par l'adresse e-mail de l'admin
        Mail::to('admin_test@example.com')->send(new NewInterventionNotification($intervention));
        return "Email de test administrateur envoyé avec succès ! Vérifiez les logs s'il y a un problème.";
    } catch (\Exception $e) {
        Log::error("Erreur lors de l'envoi de l'e-mail de test administrateur: " . $e->getMessage());
        return "Échec de l'envoi de l'e-mail de test administrateur. Vérifiez les logs. Erreur: " . $e->getMessage();
    }
});


// NOUVELLE ROUTE : Route de test pour l'envoi d'e-mail au client
Route::get('/test-client-email', function () {
    try {
        // Tente de trouver une intervention existante avec un client
        $intervention = Intervention::with('client')->has('client')->first();
        $oldStatus = 'En Attente';

        if (!$intervention) {
            // Si aucune intervention n'est trouvée, on en crée une pour le test
            $client = User::where('role', 'client')->first() ?? User::factory()->create(['role' => 'client', 'email' => 'client_test@example.com']);
            $printer = Printer::first() ?? Printer::factory()->create();
            $intervention = Intervention::create([
                'numero_demande' => 'CLIENT-TEST-' . time(),
                'start_date' => now(),
                'client_id' => $client->id,
                'printer_id' => $printer->id,
                'status' => 'En Cours', // Définissons un statut de test
                'description' => 'Test email description for client',
                'priority' => 'Haute',
                'intervention_type' => 'Matériel',
                'date_previsionnelle' => Carbon::now()->addDay(),
            ]);
        } else {
            // Pour le test, on va changer le statut de l'intervention trouvée pour simuler une mise à jour
            $oldStatus = $intervention->status;
            $intervention->status = 'En Cours'; // Simule le nouveau statut
            $intervention->save();
        }

        if (!$intervention->client) {
             return "Erreur: L'intervention trouvée n'a pas de client associé.";
        }

        // Remplacez 'client_test@example.com' par l'adresse e-mail du client de test
        Mail::to($intervention->client->email)
            ->send(new InterventionStatusUpdateNotification($intervention, $oldStatus));

        return "Email de test client envoyé avec succès à " . $intervention->client->email . " ! Vérifiez les logs s'il y a un problème.";
    } catch (\Exception $e) {
        Log::error("Erreur lors de l'envoi de l'e-mail de test client: " . $e->getMessage());
        Log::error("Stack Trace de l'erreur client: " . $e->getTraceAsString());
        return "Échec de l'envoi de l'e-mail de test client. Vérifiez les logs. Erreur: " . $e->getMessage();
    }
});
