<?php

namespace App\Http\Controllers;

use App\Models\PrinterModel;
use Illuminate\Http\Request;

class PrinterModelController extends Controller
{
    public function index(Request $request) {
        // Récupérer les paramètres de pagination et de recherche du frontend
        $perPage = $request->input('per_page', 10); // Nombre d'éléments par page, par défaut 10
        $searchTerm = $request->input('search_term', ''); // Terme de recherche, vide par défaut

                // Appliquer la recherche si un terme est fourni
                $query = PrinterModel::query();

        if (!empty($searchTerm)) {
            $query->where('name', 'like', '%' . $searchTerm . '%');
        }

        // Appliquer la pagination et retourner la réponse au format JSON
        // Laravel paginate() renvoie automatiquement la structure {data: [], total: X, last_page: Y, ...}
        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request) {
        $printerModel = PrinterModel::create($request->all());
        return response()->json($printerModel, 201);
    }

     public function update(Request $request, $id) {
        $printerModel = PrinterModel::find($id);

        if (!$printerModel) {
            return response()->json(['message' => 'Modèle introuvable'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:printer_models,name,' . $printerModel->id,
        ]);

        $printerModel->update($request->all());
        return response()->json($printerModel, 200);
    }

    public function destroy($id)
    {
        $model = PrinterModel::find($id);

        if (!$model) {
            return response()->json(['error' => 'Modèle introuvable'], 404);
        }

        $model->delete();

        return response()->json(['message' => 'Modèle supprimé avec succès']);
    }

}
