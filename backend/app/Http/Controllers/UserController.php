<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    // Liste tous les utilisateurs avec leurs sociétés et départements
    public function index()
    {
        return User::with(['company', 'department'])->get();
    }

    // Affiche un utilisateur précis avec relations
    public function show($id)
    {
        return User::with(['company', 'department'])->findOrFail($id);
    }

    // Crée un utilisateur
     public function store(Request $request)
    {
       $validated = $request->validate([
        'name' => 'required|string',
        'email' => 'required|email|unique:users',
        'password' => 'required|string|min:6',
        'role' => 'required|string',
        'department_id' => 'nullable|exists:departments,id',
        'company_id' => 'nullable|exists:companies,id',
        'status' => 'required|string',
        'lastLogin' => 'nullable|date',
        'requestsHandled' => 'nullable|string',
        'roleDisplay' => 'nullable|string',
    ]);

    $validated['password'] = Hash::make($validated['password']);
    $validated['statusDisplay'] = $validated['status']?? ucfirst($validated['status']);

    $validated['roleDisplay'] = $validated['role']?? ucfirst($validated['role']);


    $user = User::create($validated);

    return response()->json([
        'message' => 'Utilisateur créé avec succès.',
        'data' => $user
    ], 201);
    }

    // Met à jour un utilisateur
    public function update(Request $request, User $user)
    {
        $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
        'phone' => 'nullable|string|max:20',
        // 'role' => 'sometimes|string|in:admin,client,technicien', // Seul l'admin devrait pouvoir modifier ceci
    ];

    // Ajouter les règles pour le mot de passe si un nouveau mot de passe est fourni
    if ($request->filled('password')) {
        $rules['old_password'] = ['required', function ($attribute, $value, $fail) use ($user) {
            if (!Hash::check($value, $user->password)) {
                $fail('L\'ancien mot de passe est incorrect.');
            }
        }];
        $rules['password'] = 'required|string|min:8|confirmed';
        $rules['password_confirmation'] = 'required|string|min:8'; // Laravel gère 'confirmed'
    }

    $request->validate($rules);

    // Mise à jour des autres champs
    $user->name = $request->name;
    $user->email = $request->email;
    $user->phone = $request->phone;

    // Mettre à jour le rôle UNIQUEMENT si l'utilisateur est un administrateur et que le champ est présent
    if (auth()->user()->role === 'admin' && $request->has('role')) {
        $user->role = $request->role;
    }

    // Mettre à jour le mot de passe si un nouveau a été fourni et validé
    if ($request->filled('password')) {
        $user->password = Hash::make($request->password);
    }

    $user->save();

    return response()->json($user);
    }
    // Fonction utilitaire pour convertir le status en statusDisplay

    // Supprime un utilisateur
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }

    public function changePassword(Request $request, User $user)
    {
        // Ensure the authenticated user is authorized to change this password
        // An admin can change any user's password.
        // A user can only change their own password.
        if (Auth::id() !== $user->id && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized to change this password.'], 403);
        }

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed', // 'confirmed' checks for new_password_confirmation
        ]);

        // Verify the current password for non-admin users changing their own password
        // Admins can bypass current password check when changing other users' passwords if desired,
        // but for a user changing their own, it's mandatory.
        if (Auth::id() === $user->id && !Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        // Update the password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Mot de passe mis à jour avec succès.'], 200);
    }

}
