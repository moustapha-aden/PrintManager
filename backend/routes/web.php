<?php

use App\Mail\InterventionStatusUpdateNotification; // Nouvelle classe Mailable pour le client
use App\Mail\NewInterventionNotification;
use App\Models\Intervention;
use App\Models\Printer; // Assurez-vous que le modèle Printer est importé pour la création de test
use App\Models\PrinterQuota;
use App\Models\User; // Assurez-vous que le modèle User est importé pour la création de test
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;


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





Route::get('/rapport', function () {

    $startDate = now()->startOfMonth()->format('Y-m-d'); // exemple
    $endDate   = now()->endOfMonth()->format('Y-m-d');

    $quotas = PrinterQuota::with(['printer.company', 'printer.department'])
        ->whereBetween('mois', [$startDate, $endDate])
        ->get();

    $company = null;
    $department = null;

    if ($quotas->isNotEmpty()) {
        $firstQuota = $quotas->first();
        $company = $firstQuota->printer->company ?? null;
        $department = $firstQuota->printer->department ?? null;
    }

    return view('reports.quota_report', compact(
        'quotas', 'startDate', 'endDate', 'company', 'department'
    ));
});
