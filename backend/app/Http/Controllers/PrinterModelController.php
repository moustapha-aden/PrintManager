<?php

namespace App\Http\Controllers;

use App\Models\PrinterModel;
use Illuminate\Http\Request;

class PrinterModelController extends Controller
{
    public function index() {
        return response()->json(PrinterModel::all());
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
