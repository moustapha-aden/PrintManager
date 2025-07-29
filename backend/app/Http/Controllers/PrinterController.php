<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Printer;
use App\Models\PrinterMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule; // Importez la classe Rule pour les validations "in"

class PrinterController extends Controller
{
    /**
     * Affiche la liste des imprimantes avec relations, et filtres optionnels.
     */
    public function index(Request $request)
    {
        $query = Printer::query();

        // Charger les relations nécessaires
        $query->with(['company', 'department', 'interventions', 'interventions.technician']);

        // NOUVEAU: Filtrer par company_id (utile pour les clients liés à une entreprise)
        if ($request->has('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }

        // NOUVEAU: Filtrer par department_id (utile pour les clients ou pour affiner)
        if ($request->has('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        // NOUVEAU: Filtrer par statut (par exemple, pour afficher les imprimantes "en panne" pour un technicien)
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Ajoutez d'autres filtres si nécessaire (ex: par marque, modèle, etc.)

        return $query->get();
    }

    /**
     * Affiche une seule imprimante avec ses relations.
     */
    public function show($id)
    {
        return Printer::with(['company', 'department', 'interventions', 'interventions.technician'])->findOrFail($id);
    }

    /**
     * Enregistre une nouvelle imprimante.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'model' => 'required|string|max:255',
            'brand' => 'required|string|max:255',
            'serial' => 'required|string|max:255|unique:printers',
            // NOUVEAU: Validation 'in' pour le statut
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'En maintenance', 'hors-service'])],
            'company_id' => 'required|exists:companies,id',
            'department_id' => 'required|exists:departments,id',
            'installDate' => 'sometimes|date',
        ]);

        // Supprimez cette ligne si statusDisplay n'est pas une colonne de DB
        // Si c'est une colonne de DB, assurez-vous que la valeur est correctement assignée ou traduite si besoin
         $validated['statusDisplay'] = $validated['status'] ?? ucfirst($validated['status']);
        // Si statusDisplay est une colonne en DB, il vaut mieux le laisser au frontend gérer l'affichage ou utiliser un mutator Laravel.
        // Si vous voulez juste stocker le même statut que 'status', alors :
        // $validated['statusDisplay'] = $validated['status']; // Si statusDisplay est une colonne distincte

        $printer = Printer::create($validated);

        return response()->json($printer, 201);
    }

    /**
     * Met à jour une imprimante existante.
     */
    public function update(Request $request, $id)
    {
        $printer = Printer::findOrFail($id);

        $validated = $request->validate([
            'model' => 'sometimes|string|max:255',
            'brand' => 'sometimes|string|max:255',
            'serial' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('printers')->ignore($printer->id),
            ],
            // NOUVEAU: Validation 'sometimes|in' pour le statut
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'En maintenance', 'hors-service'])],
            // Si statusDisplay est une colonne en DB et doit être mise à jour
            'statusDisplay' => 'sometimes|string|max:255',
            'company_id' => 'sometimes|exists:companies,id',
            'department_id' => 'sometimes|exists:departments,id',
            'installDate' => 'sometimes|date',
        ]);

        // Si statusDisplay est une colonne de DB et doit être mise à jour avec la valeur de 'status'
        if (isset($validated['status'])) {
            $validated['statusDisplay'] = $validated['status'];
        }

        $printer->update($validated);

        return response()->json($printer, 200);
    }

    /**
     * Supprime une imprimante.
     */
    public function destroy($id)
    {
        $printer = Printer::findOrFail($id);

        // Vérifiez si l'imprimante a des interventions associées avant de supprimer
        if ($printer->interventions()->exists()) {
            return response()->json(['message' => 'Impossible de supprimer cette imprimante car elle a des interventions associées.'], 409); // Conflit
        }

        $printer->delete();

        return response()->json(['message' => 'Imprimante supprimée avec succès.']);
    }

    /**
     * Déplace une imprimante vers un nouveau département.
     */
    public function move(Request $request, Printer $printer)
    {
        $request->validate([
            'new_department_id' => 'required|exists:departments,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $oldDepartmentId = $printer->department_id; // Ancien ID du département

        // Assurez-vous que le nouveau département n'est pas le même que l'ancien
        if ($oldDepartmentId == $request->new_department_id) {
            return response()->json(['message' => 'L\'imprimante est déjà dans ce département.'], 400);
        }

        DB::beginTransaction(); // Démarre une transaction pour garantir l'atomicité
        try {
            // Récupérer le nouveau département pour sa company_id
            $newDepartment = Department::findOrFail($request->new_department_id);

            // 1. Mettre à jour le département et la compagnie de l'imprimante
            $printer->department_id = $newDepartment->id;
            $printer->company_id = $newDepartment->company_id; // Mettre à jour la company_id de l'imprimante également
            $printer->save();

            // 2. Enregistrer le mouvement dans la table printer_movements
            PrinterMovement::create([
                'printer_id' => $printer->id,
                'old_department_id' => $oldDepartmentId,
                'new_department_id' => $newDepartment->id,
                'moved_by_user_id' => auth()->check() ? auth()->id() : null, // ID de l'utilisateur connecté, si disponible
                'notes' => $request->notes,
                'date_mouvement' => now(), // Date du mouvement, par défaut à la date actuelle
            ]);

            DB::commit(); // Valide la transaction

            return response()->json([
                'message' => 'Imprimante déplacée avec succès.',
                'printer' => $printer->load('department.company'), // Recharger les relations pour le frontend
            ]);

        } catch (\Exception $e) {
            DB::rollBack(); // Annule la transaction en cas d'erreur
            Log::error("Erreur lors du déplacement de l'imprimante: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Une erreur est survenue lors du déplacement de l\'imprimante.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Récupère l'historique des mouvements d'imprimantes.
     */
    public function getPrinterMovements()
    {
        return PrinterMovement::with([
            'printer',
            'oldDepartment.company', // Charger la société de l'ancien département
            'newDepartment.company', // Charger la société du nouveau département
            'movedBy'
        ])
        ->orderByDesc('created_at') // Ordonner du plus récent au plus ancien
        ->get();
    }

    /**
     * Récupère les imprimantes par département.
     * Peut être utile pour le client ou pour des vues filtrées.
     */
    public function printersByDepartment($departmentId)
    {
        return Printer::where('department_id', $departmentId)->with(['company', 'department'])->get();
    }

      public function search(Request $request)
    {
        // Change ici : récupère ' numero_demande' au lieu de 'serialNumber'
        $numero_demande = $request->query('numero_demande');

        if (!$numero_demande) {
            return response()->json(['message' => 'Veuillez fournir un numéro de demande.'], 400);
        }

        $printer = Printer::with([
            'company:id,name', // Charge la relation company et sélectionne seulement id et name
            'interventions' => function ($query) {
                // Charge les interventions et leurs utilisateurs associés
                $query->with(['assignedTo:id,name', 'reportedBy:id,name'])
                      ->orderByDesc('created_at')
                      ->limit(5); // Limite le nombre d'interventions récentes pour la recherche
            }
        ])
        // Change ici : recherche dans la colonne 'numero_demande'
        ->where('numero_demande', $numero_demande)
        ->first(); // Utilise first() car tu t'attends à une seule imprimante par numéro de demande (qui devrait être unique)

        if (!$printer) {
            return response()->json(['message' => 'Imprimante non trouvée pour ce numéro de demande.'], 404);
        }

        // Formater la réponse pour qu'elle corresponde à ce que le frontend attend
        return response()->json([
            'id' => $printer->id,
            'model' => $printer->model,
            'serialNumber' => $printer->serialNumber, // Garde le numéro de série dans la réponse
            'status' => $printer->status,
            'location' => $printer->location ?? 'N/A',
            ' numero_demande' => $printer-> numero_demande, // Ajoute le numéro de demande dans la réponse
            'companyName' => $printer->company->name ?? 'N/A',
            'interventions' => $printer->interventions->map(function ($intervention) {
                return [
                    'id' => $intervention->id,
                    'description' => $intervention->description,
                    'status' => $intervention->status,
                    'created_at' => $intervention->created_at->format('Y-m-d H:i'),
                    'assigned_to_user' => $intervention->assignedTo ? ['id' => $intervention->assignedTo->id, 'name' => $intervention->assignedTo->name] : null,
                    'reported_by_user' => $intervention->reportedBy ? ['id' => $intervention->reportedBy->id, 'name' => $intervention->reportedBy->name] : null,
                ];
            }),
        ]);
    }
}
