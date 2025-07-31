<?php

namespace App\Http\Controllers;

use App\Models\Intervention;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB; // Importez le facade DB

class InterventionController extends Controller
{
    /**
     * Liste toutes les interventions avec relations, avec filtres optionnels.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Intervention::query();

        // Charger les relations nécessaires
        $query->with([
            'client.company',
            'client.department',
            'printer.company',
            'printer.department',
            'technician'
        ]);

        // Logique de filtrage basée sur le rôle de l'utilisateur
        $user = Auth::user();

        if ($user) {
            if ($user->role === 'client') {
                // Un client ne voit que ses propres interventions
                $query->where('client_id', $user->id);
            } elseif ($user->role === 'technicien') {
                // Un technicien voit ses interventions assignées OU les interventions en statut 'En Attente'
                $query->where(function($q) use ($user) {
                    $q->where('technician_id', $user->id);
                    //   ->orWhere('status', 'En Attente'); // Un technicien peut aussi voir les nouvelles demandes
                });
            }
            // Les administrateurs voient toutes les interventions par défaut
        }

        // Ajoutez ici les filtres de recherche et de statut
        if ($request->has('status_filter') && $request->input('status_filter') !== 'all') {
            $query->where('status', $request->input('status_filter'));
        }

        if ($request->has('priority_filter') && $request->input('priority_filter') !== 'all') {
            $query->where('priority', $request->input('priority_filter'));
        }

        if ($request->has('intervention_type_filter') && $request->input('intervention_type_filter') !== 'all') {
            $query->where('intervention_type', $request->input('intervention_type_filter'));
        }

        if ($request->has('search_term')) {
            $searchTerm = strtolower($request->input('search_term'));
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(description) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhereRaw('LOWER(status) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhereRaw('LOWER(priority) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhereRaw('LOWER(intervention_type) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhere('id', 'like', "%{$searchTerm}%")
                  ->orWhere('numero_demande', 'like', "%{$searchTerm}%")
                  ->orWhereHas('printer', function ($pq) use ($searchTerm) {
                      $pq->whereRaw('LOWER(model) LIKE ?', ["%{$searchTerm}%"])
                         ->orWhereRaw('LOWER(brand) LIKE ?', ["%{$searchTerm}%"])
                         ->orWhereRaw('LOWER(serial) LIKE ?', ["%{$searchTerm}%"])
                         ->orWhereHas('company', function ($cq) use ($searchTerm) {
                             $cq->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"]);
                         })
                         ->orWhereHas('department', function ($dq) use ($searchTerm) {
                             $dq->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"]);
                         });
                  })
                  ->orWhereHas('technician', function ($tq) use ($searchTerm) {
                      $tq->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"]);
                  })
                  ->orWhereHas('client', function ($clq) use ($searchTerm) {
                      $clq->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                          ->orWhereHas('company', function ($ccq) use ($searchTerm) {
                              $ccq->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"]);
                          })
                          ->orWhereHas('department', function ($cdq) use ($searchTerm) {
                              $cdq->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"]);
                          });
                  });
            });
        }

        // Optionnel: Ajouter un orderBy par défaut si non spécifié
        $query->orderByDesc('created_at');

        return response()->json($query->get());
    }

    /**
     * Affiche une intervention précise avec relations.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $intervention = Intervention::with([
            'printer.company',
            'printer.department',
            'client.company',
            'client.department',
            'technician'
        ])->findOrFail($id);

        return response()->json($intervention, 200);
    }

    /**
     * Crée une nouvelle intervention.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'numero_demande' => 'required|string|unique:interventions,numero_demande|max:255',
            'start_date' => 'required|date',
            'client_id' => 'nullable|exists:users,id',
            'technician_id' => 'nullable|exists:users,id',
            'printer_id' => 'required|exists:printers,id',
            // Statuts avec accent pour la cohérence DB/API
            'status' => ['required', 'string', Rule::in(['En Attente', 'En Cours', 'Terminée', 'Annulée'])],
            'description' => 'nullable|string|max:1000',
            // Priorités sans accent pour la cohérence DB/API (ajout de 'Faible' et 'Urgent' si elles existent dans votre DB)
            'priority' => ['required', 'string', Rule::in(['Haute', 'Moyenne', 'Basse', 'Faible', 'Urgent'])],
            'intervention_type' => 'required|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date_previsionnelle' => 'nullable|date|after_or_equal:start_date',
            'solution' => 'nullable|string|max:1000',
        ]);

        if (empty($validated['technician_id'])) {
            $validated['technician_id'] = 5;
        }

        $intervention = new Intervention($validated);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('public/interventions_photos');
            $intervention->image_path = str_replace('public/', '', $path);
        }

        $intervention->save();

        return response()->json($intervention->load(['client', 'technician', 'printer']), 201);
    }

    /**
     * Met à jour une intervention existante.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $intervention = Intervention::findOrFail($id);

        $validated = $request->validate([
            'numero_demande' => ['sometimes', 'string', 'max:255', Rule::unique('interventions', 'numero_demande')->ignore($id)],
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'client_id' => 'nullable|exists:users,id',
            'technician_id' => 'nullable|exists:users,id',
            'printer_id' => 'sometimes|exists:printers,id',
            // Statuts avec accent pour la cohérence DB/API
            'status' => ['sometimes', 'string', Rule::in(['En Attente', 'En Cours', 'Terminée', 'Annulée'])],
            'description' => 'nullable|string|max:1000',
            // Priorités sans accent pour la cohérence DB/API (ajout de 'Faible' et 'Urgent' si elles existent dans votre DB)
            'priority' => ['sometimes', 'string', Rule::in(['Haute', 'Moyenne', 'Basse', 'Faible', 'Urgent'])],
            'notes' => 'nullable|string|max:1000',
            'intervention_type' => 'sometimes|string|max:255',
            'solution' => 'nullable|string|max:1000',
            'date_previsionnelle' => 'nullable|date|after_or_equal:start_date',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'delete_photo' => 'sometimes|boolean',
        ]);

        if ($request->boolean('delete_photo') && $intervention->image_path) {
            $fullPathToDelete = 'public/' . $intervention->image_path;
            if (Storage::exists($fullPathToDelete)) {
                Storage::delete($fullPathToDelete);
            }
            $intervention->image_path = null;
        }

        if ($request->hasFile('photo')) {
            if ($intervention->image_path) {
                $fullPathToDelete = 'public/' . $intervention->image_path;
                if (Storage::exists($fullPathToDelete)) {
                    Storage::delete($fullPathToDelete);
                }
            }
            $path = $request->file('photo')->store('public/interventions_photos');
            $intervention->image_path = str_replace('public/', '', $path);
        }

        $intervention->fill($validated);
        $intervention->save();

        return response()->json($intervention->load(['client', 'technician', 'printer']), 200);
    }

    /**
     * Supprime une intervention.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $intervention = Intervention::findOrFail($id);

        if ($intervention->image_path) {
            $photoPath = 'public/' . $intervention->image_path;
            if (Storage::exists($photoPath)) {
                Storage::delete($photoPath);
            }
        }

        $intervention->delete();

        return response()->json(['message' => 'Intervention supprimée avec succès.']);
    }

    /**
     * Récupère les statistiques d'interventions par statut.
     * Utile pour les tableaux de bord.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInterventionStatistics()
    {
        $totalInterventionCount = Intervention::count();

        $interventionsStatusCounts = Intervention::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Assurez-vous que les clés de retour correspondent à ce que le frontend attend (sans accent si nécessaire)
        // et que les valeurs proviennent des clés de la DB (avec accent).
        $interventionsStatus = [
            'Terminee' => $interventionsStatusCounts['Terminée'] ?? 0,
            'En Attente' => $interventionsStatusCounts['En Attente'] ?? 0,
            'En Cours' => $interventionsStatusCounts['En Cours'] ?? 0,
            'Annulee' => $interventionsStatusCounts['Annulée'] ?? 0,
        ];

        return response()->json([
            'total' => $totalInterventionCount,
            'Terminee' => $interventionsStatus['Terminee'],
            'En Attente' => $interventionsStatus['En Attente'],
            'En Cours' => $interventionsStatus['En Cours'],
            'Annulee' => $interventionsStatus['Annulee'],
        ]);
    }

    /**
     * Récupère les interventions par période (ex: jour, semaine, mois).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInterventionsByPeriod(Request $request)
    {
        $period = $request->query('period', 'day'); // 'day', 'week', 'month', 'year'
        $query = Intervention::query();

        switch ($period) {
            case 'day':
                $query->whereDate('created_at', now()->toDateString());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
                break;
            case 'year':
                $query->whereYear('created_at', now()->year);
                break;
            default:
                // Pas de filtre par période si la période n'est pas reconnue
                break;
        }

        return response()->json($query->get());
    }
}
