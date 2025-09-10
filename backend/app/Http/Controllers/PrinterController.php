<?php

namespace App\Http\Controllers;

use App\Models\Printer;
use App\Models\Department;
use App\Models\PrinterMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class PrinterController extends Controller
{
    /**
     * Display a listing of the resource.
     * Gère le filtrage par statut, compagnie, département, non attribuées, en stock et retournées entrepôt.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Printer::query();
        $user = $request->user();
        // Charger les relations nécessaires pour l'affichage
        $query->with(['company', 'department', 'interventions', 'interventions.technician']);

        // Filtrage spécifique pour clients
        if ($user->role === 'client') {
            if ($user->department_id) {
                $query->where('department_id', $user->department_id);
            } elseif ($user->company_id) {
                $query->where('company_id', $user->company_id);
            } else {
                return response()->json([]);
            }
        } else {
            // Pour l'admin ou technicien, appliquer les filtres via query params
            if ($request->has('department_id') && $request->input('department_id') !== 'all') {
                $query->where('department_id', $request->input('department_id'));
            }
            if ($request->has('company_id') && $request->input('company_id') !== 'all') {
                $query->where('company_id', $request->input('company_id'));
            }
        }

        // Récupérer l'ID du département "Entrepôt" une seule fois
        $warehouseDepartment = Department::where('name', 'Entrepôt')->first();
        $warehouseDepartmentId = $warehouseDepartment ? $warehouseDepartment->id : null;

        // Prioriser les filtres spécifiques basés sur des requêtes dérivées
        if ($request->has('unassigned') && filter_var($request->input('unassigned'), FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNull('company_id')->whereNull('department_id');
        } elseif ($request->has('returned_to_warehouse_filter') && filter_var($request->input('returned_to_warehouse_filter'), FILTER_VALIDATE_BOOLEAN)) {
            $query->where('is_returned_to_warehouse', true);
        } elseif ($request->has('in_stock_filter') && filter_var($request->input('in_stock_filter'), FILTER_VALIDATE_BOOLEAN)) {
            if ($warehouseDepartmentId) {
                $query->where('department_id', $warehouseDepartmentId)
                      ->where('status', 'inactive');
            } else {
                Log::warning("Demande de filtre 'en_stock' mais le département 'Entrepôt' est introuvable.");
                return response()->json([]);
            }
        } else {
            // Appliquer les filtres généraux si les filtres spécifiques ne sont pas actifs

            // --- NOUVELLE LOGIQUE DE FILTRAGE POUR 'is_purchased' ---
            if ($request->has('is_purchased') && $request->input('is_purchased') !== 'all') {
                $isPurchased = filter_var($request->input('is_purchased'), FILTER_VALIDATE_BOOLEAN);
                $query->where('is_purchased', $isPurchased);
            }
            // --- FIN DE LA NOUVELLE LOGIQUE ---

            if ($request->has('status') && $request->input('status') !== 'all') {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('company_id') && $request->input('company_id') !== 'all') {
                $query->where('company_id', $request->input('company_id'));
            }

            if ($request->has('department_id') && $request->input('department_id') !== 'all') {
                $query->where('department_id', $request->input('department_id'));
            }
        }

        if ($request->has('search_term') && !empty($request->input('search_term'))) {
            $searchTerm = $request->input('search_term');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('model', 'like', '%' . $searchTerm . '%')
                  ->orWhere('brand', 'like', '%' . $searchTerm . '%')
                  ->orWhere('serial', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('company', function ($q2) use ($searchTerm) {
                      $q2->where('name', 'like', '%' . $searchTerm . '%');
                  })
                  ->orWhereHas('department', function ($q2) use ($searchTerm) {
                      $q2->where('name', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        $printers = $query->get();

        $printers->each(function ($printer) {
            $printer->statusDisplay = $this->getStatusDisplay($printer->status);
        });

        return response()->json($printers);
    }

    /**
     * Affiche une seule imprimante avec ses relations.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return Printer::with(['company', 'department', 'interventions', 'interventions.technician'])->findOrFail($id);
    }

    /**
     * Enregistre une nouvelle imprimante.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'model' => 'required|string|max:255',
            'brand' => 'required|string|max:255',
            'serial' => 'required|string|max:255|unique:printers',
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'maintenance', 'hors-service'])],
            'company_id' => 'nullable|exists:companies,id',
            'department_id' => 'nullable|exists:departments,id',
            'installDate' => 'sometimes|date',
            'is_returned_to_warehouse' => 'boolean',
            // --- NOUVEAU: Validation pour le champ is_purchased ---
            'is_purchased' => 'required|boolean',
            // --- FIN DE LA NOUVELLE VALIDATION ---
        ]);

        $warehouseDepartment = Department::where('name', 'Entrepôt')->first();
        if ($warehouseDepartment && $validated['department_id'] == $warehouseDepartment->id) {
            // $validated['company_id'] = null;
            // $validated['is_purchased'] = "entrepot";
        }

        if ($request->has('is_returned_to_warehouse')) {
            $validated['is_returned_to_warehouse'] = filter_var($request->input('is_returned_to_warehouse'), FILTER_VALIDATE_BOOLEAN);
        }

        // --- NOUVEAU: Utiliser la valeur validée directement ---
        if ($request->has('is_purchased')) {
            $validated['is_purchased'] = filter_var($request->input('is_purchased'), FILTER_VALIDATE_BOOLEAN);
        }
        // --- FIN DE LA NOUVELLE GESTION ---

        $printer = Printer::create($validated);

        return response()->json($printer, 201);
    }

    /**
     * Met à jour une imprimante existante.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
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
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'maintenance', 'hors-service'])],
            'company_id' => 'nullable|exists:companies,id',
            'department_id' => 'nullable|exists:departments,id',
            'installDate' => 'sometimes|date',
            'is_returned_to_warehouse' => 'sometimes|boolean',
            // --- NOUVEAU: Validation pour le champ is_purchased ---
            'is_purchased' => 'sometimes|boolean',
            // --- FIN DE LA NOUVELLE VALIDATION ---
        ]);

        if ($request->has('is_returned_to_warehouse')) {
            $validated['is_returned_to_warehouse'] = filter_var($request->input('is_returned_to_warehouse'), FILTER_VALIDATE_BOOLEAN);
        }

        // --- NOUVEAU: Gérer la mise à jour du champ is_purchased ---
        if ($request->has('is_purchased')) {
            $validated['is_purchased'] = filter_var($request->input('is_purchased'), FILTER_VALIDATE_BOOLEAN);
        }
        // --- FIN DE LA NOUVELLE GESTION ---

        $printer->update($validated);

        return response()->json($printer, 200);
    }

    /**
     * Supprime une imprimante.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $printer = Printer::findOrFail($id);

        if ($printer->interventions()->exists()) {
            return response()->json(['message' => 'Impossible de supprimer cette imprimante car elle a des interventions associées.'], 409);
        }

        $printer->delete();

        return response()->json(['message' => 'Imprimante supprimée avec succès.']);
    }

    /**
     * Déplace une imprimante vers un nouveau département.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Printer  $printer
     * @return \Illuminate\Http\JsonResponse
     */
    public function move(Request $request, Printer $printer)
    {
        $validated = $request->validate([
            'new_department_id' => 'required|exists:departments,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $oldDepartmentId = $printer->department_id;
        $oldCompanyId = $printer->company_id;

        if ($oldDepartmentId == $validated['new_department_id']) {
            return response()->json([
                'message' => 'L\'imprimante est déjà dans ce département.'
            ], 409);
        }

        DB::beginTransaction();

        try {
            $newDepartment = Department::findOrFail($validated['new_department_id']);
            $newCompanyId = $newDepartment->company_id;

            $newStatus = $printer->status;
            $isReturnedToWarehouse = false;

            $warehouseDepartment = Department::where('name', 'Entrepôt')->first();

            if (!$warehouseDepartment) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Le département "Entrepôt" est introuvable. Veuillez le créer ou vérifier son nom.'
                ], 500);
            }

            if ($newDepartment->id == $warehouseDepartment->id) {
                $isReturnedToWarehouse = true;
                if ($newStatus === 'active') {
                    $newStatus = 'inactive';
                }
            } else {
                $isReturnedToWarehouse = false;
                if ($newStatus === 'inactive' || $printer->is_returned_to_warehouse) {
                    $newStatus = 'active';
                }
            }

            $printer->update([
                'department_id' => $newDepartment->id,
                'company_id' => $newCompanyId,
                'is_returned_to_warehouse' => $isReturnedToWarehouse,
                'status' => $newStatus,
                'is_purchased' => 'sometimes|boolean',
            ]);

            PrinterMovement::create([
                'printer_id' => $printer->id,
                'old_department_id' => $oldDepartmentId,
                'new_department_id' => $newDepartment->id,
                'moved_by_user_id' => auth()->check() ? auth()->id() : null,
                'notes' => $validated['notes'],
                'date_mouvement' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Imprimante déplacée avec succès.',
                'printer' => $printer->load(['department', 'company']),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur lors du déplacement de l'imprimante: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'printer_id' => $printer->id,
                'request_data' => $request->all(),
            ]);
            return response()->json([
                'message' => 'Une erreur est survenue lors du déplacement de l\'imprimante.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère l'historique des mouvements d'imprimantes.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPrinterMovements()
    {
        return PrinterMovement::with([
            'printer',
            'oldDepartment.company',
            'newDepartment.company',
            'movedBy'
        ])
        ->orderByDesc('created_at')
        ->get();
    }

    /**
     * Récupère les compteurs d'imprimantes non attribuées, en stock et retournées à l'entrepôt.
     * Cette méthode est appelée par la route /api/printers/counts
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPrinterCounts(Request $request)
    {
        $unassignedCount = Printer::whereNull('company_id')
                                   ->whereNull('department_id')
                                   ->count();

        $warehouseDepartment = Department::where('name', 'Entrepôt')->first();
        $warehouseDepartmentId = $warehouseDepartment ? $warehouseDepartment->id : null;

        $inStockCount = 0;
        if ($warehouseDepartmentId) {
            $inStockCount = Printer::where('department_id', $warehouseDepartmentId)
                                   ->where('status', 'inactive')
                                   ->count();
        }

        $returnedToWarehouseCount = Printer::where('is_returned_to_warehouse', true)->count();

        return response()->json([
            'unassigned_count' => $unassignedCount,
            'returned_count' => $returnedToWarehouseCount,
            'in_stock_count' => $inStockCount,
        ]);
    }

    /**
     * Recherche une imprimante par numéro de demande.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $numero_demande = $request->query('numero_demande');

        if (!$numero_demande) {
            return response()->json(['message' => 'Veuillez fournir un numéro de demande.'], 400);
        }

        $printer = Printer::with([
            'company:id,name',
            'interventions' => function ($query) {
                $query->with(['assignedTo:id,name', 'reportedBy:id,name'])
                      ->orderByDesc('created_at')
                      ->limit(5);
            }
        ])
        ->where('numero_demande', $numero_demande)
        ->first();

        if (!$printer) {
            return response()->json(['message' => 'Imprimante non trouvée pour ce numéro de demande.'], 404);
        }

        return response()->json([
            'id' => $printer->id,
            'model' => $printer->model,
            'serialNumber' => $printer->serial,
            'status' => $printer->status,
            'location' => $printer->location ?? 'N/A',
            'numero_demande' => $printer->numero_demande,
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

    /**
     * Helper function to get display status.
     *
     * @param  string  $status
     * @return string
     */
    private function getStatusDisplay($status) {
        switch ($status) {
            case 'active': return 'Active';
            case 'maintenance': return 'En maintenance';
            case 'hors-service': return 'Hors service';
            case 'inactive': return 'Inactive';
            default: return ucfirst($status);
        }
    }
}
