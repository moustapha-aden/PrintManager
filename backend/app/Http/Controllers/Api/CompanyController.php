<?php

namespace App\Http\Controllers\Api; // Assurez-vous que ce namespace est correct

use App\Http\Controllers\Controller;
use App\Models\Company; // Importez votre modèle Company
use Illuminate\Http\Request; // Importez la classe Request
use Illuminate\Support\Facades\Log; // Utile pour le débogage

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        // Récupère toutes les sociétés.
        // Le filtrage par statut 'active' se fera côté frontend dans AddUserForm.js.
        return response()->json(Company::all());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        // Valide les données entrantes pour la création d'une société
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:companies,name', // Le nom doit être unique
            'address' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255', // Ajouté selon votre migration
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255',
            'contact_person' => 'nullable|string|max:255', // Ajouté selon votre migration
            'status' => 'nullable|string|', // Le statut est maintenant géré
            'quota_BW'=>'required|integer',
            'quota_Color'=>'required|integer',
            'quota_monthly'=>'required|integer',
        ]);

        try {
            // Crée une nouvelle société avec les données validées
            $company = Company::create($validatedData);
            // Retourne la société créée avec un statut 201 Created
            return response()->json($company, 201);
        } catch (\Exception $e) {
            // Enregistre l'erreur pour le débogage
            Log::error('Error creating company: ' . $e->getMessage(), ['exception' => $e]);
            // Retourne une réponse d'erreur 500 Internal Server Error
            return response()->json(['message' => 'Failed to create company.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id): \Illuminate\Http\JsonResponse
    {
        // Trouve la société par son ID ou lance une exception 404 si non trouvée
        return response()->json(Company::findOrFail($id));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        // Trouve la société à mettre à jour
        $company = Company::findOrFail($id);

        // Valide les données entrantes pour la mise à jour
        // 'sometimes' assure que le champ n'est validé que s'il est présent dans la requête
        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:companies,name,' . $id, // Unique sauf pour l'ID actuel
            'address' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255', // Ajouté selon votre migration
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255',
            'contact_person' => 'nullable|string|max:255', // Ajouté selon votre migration
            'status' => 'sometimes|required|string|', // Le statut est maintenant géré
            'quota_BW'=>'sometimes|integer',
            'quota_Color'=>'sometimes|integer',
            'quota_monthly'=>'sometimes|integer',
        ]);

        try {
            // Met à jour la société avec les données validées
            $company->update($validatedData);
            // Retourne la société mise à jour
            return response()->json($company);
        } catch (\Exception $e) {
            Log::error('Error updating company: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Failed to update company.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id): \Illuminate\Http\JsonResponse
    {
        try {
            $company = Company::findOrFail($id);

            // Vérifier si la société a des départements, utilisateurs ou imprimantes liés avant de supprimer
            // Ces relations doivent être définies dans votre modèle Company.php
            if ($company->departments()->exists() || $company->users()->exists() || $company->printers()->exists()) {
                return response()->json(['message' => 'Cannot delete company with associated departments, users, or printers.'], 409); // Conflict
            }

            // Supprime la société
            $company->delete();
            // Retourne un statut 204 No Content pour une suppression réussie
            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error('Error deleting company: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Failed to delete company.', 'error' => $e->getMessage()], 500);
        }
    }
}
