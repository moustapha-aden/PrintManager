<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    // Liste des marques
    public function index(Request $request) {
        // Récupérer les paramètres de pagination et de recherche du frontend
        $perPage = $request->input('per_page', 10); // Nombre d'éléments par page, par défaut 10
        $searchTerm = $request->input('search_term', ''); // Terme de recherche, vide par défaut

        // Appliquer la recherche si un terme est fourni
        $query = Brand::query();

        if (!empty($searchTerm)) {
            $query->where('name', 'like', '%' . $searchTerm . '%');
        }

        // Appliquer la pagination et retourner la réponse au format JSON
        // Laravel paginate() renvoie automatiquement la structure {data: [], total: X, last_page: Y, ...}
        return response()->json($query->paginate($perPage));
    }


    // Création d'une marque
    public function store(Request $request) {
        $request->validate([
            'name' => 'required|string|max:255|unique:brands,name',
        ]);

        $brand = Brand::create($request->all());
        return response()->json($brand, 201);
    }

    // Mise à jour d'une marque
    public function update(Request $request, $id) {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json(['message' => 'Marque non trouvée.'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:brands,name,' . $brand->id,
        ]);

        $brand->update($request->all());
        return response()->json($brand, 200);
    }

    // Suppression d'une marque
    public function destroy($id) {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json(['message' => 'Marque non trouvée.'], 404);
        }

        try {
            $brand->delete();
            return response()->json(['message' => 'Marque supprimée avec succès.'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de la marque.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
