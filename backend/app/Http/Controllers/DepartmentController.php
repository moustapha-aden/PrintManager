<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {

        $query = Department::query();

        // Si company_id est présent dans la requête, filtrer les départements par cette company_id
        if ($request->has('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }

        return $query->get();

    }

    public function show($id)
    {
        return Department::with('company')->findOrFail($id);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'company_id' => 'required|exists:companies,id',
        ]);

        $department = Department::create($validated);

        return response()->json($department, 201);
    }

    public function update(Request $request, $id)
    {
        $department = Department::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'company_id' => 'sometimes|exists:companies,id',
        ]);

        $department->update($validated);

        return response()->json($department, 200);
    }

    public function destroy($id)
    {
        $department = Department::findOrFail($id);
        $department->delete();

        return response()->json(['message' => 'Département supprimé.']);
    }
}
