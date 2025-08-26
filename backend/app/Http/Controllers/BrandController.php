<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    // Liste des marques
    public function index() {
        return response()->json(Brand::all());
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
