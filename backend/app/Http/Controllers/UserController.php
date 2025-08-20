<?php

namespace App\Http\Controllers;

use App\Mail\UserCredentialsMail;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // Liste tous les utilisateurs avec leurs sociétés et départements
    public function index(Request $request) // Ajoutez Request $request ici
    {
        $query = User::query();

        // Si un paramètre 'role' est fourni dans la requête, filtre par ce rôle
        if ($request->has('role')) {
            $roles = explode(',', $request->input('role')); // Permet de passer plusieurs rôles séparés par des virgules (ex: ?role=admin,technicien)
            $query->whereIn('role', $roles);
        }

        // Si tu veux spécifiquement les techniciens et admins pour l'assignation,
        // tu peux aussi faire ceci si aucun rôle n'est spécifié, ou si tu veux une route dédiée
        // if (!$request->has('role')) { // Si aucun rôle n'est spécifié, retourne tous les utilisateurs ou seulement admin/technicien
        //     $query->whereIn('role', ['admin', 'technicien']); // Exemple: si tu veux par défaut seulement ces rôles
        // }


        // Charger les relations nécessaires
        $query->with(['company', 'department']);

        // Retourne les utilisateurs filtrés (ou tous si aucun filtre de rôle)
        return $query->get();
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

    $plainPassword=$validated['password'];
    $validated['password'] = Hash::make($validated['password']);
    $validated['statusDisplay'] = $validated['status']?? ucfirst($validated['status']);

    $validated['roleDisplay'] = $validated['role']?? ucfirst($validated['role']);

    $validated['company_id'] = $validated['company_id'] ?? 1;       // Ex: company_id = 1
    $validated['department_id'] = $validated['department_id'] ?? 1; // Ex: department_id = 1

    $user = User::create($validated);

    // 3. Envoi de l'e-mail avec les informations de connexion
    Mail::to($user->email)->send(new UserCredentialsMail($user, $plainPassword));

    return response()->json([
        'message' => 'Utilisateur créé avec succès.',
        'data' => $user
    ], 201);
    }

    // Met à jour un utilisateur

    // Met à jour un utilisateur (y compris le changement de mot de passe)

    // Met à jour un utilisateur (y compris le changement de mot de passe)
    public function update(Request $request, User $user)
    {


        // 2. Définition des règles de validation
        $rules = [
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'phone' => 'sometimes|nullable|string|max:20|regex:/^[\d\s\+\-\(\)\.]+$/',
            'role' => 'sometimes|required|string|in:admin,client,technicien',
            'company_id' => 'nullable|exists:companies,id',
            'department_id' => 'nullable|exists:departments,id',
            'status' => 'sometimes|required|string|in:active,inactive',
            'lastLogin' => 'nullable|date',
            'requestsHandled' => 'nullable|string',
        ];

        // 3. Règles spécifiques pour le changement de mot de passe
        if ($request->filled('password')) {
            $rules['password'] = 'string|min:8|confirmed';
            $rules['password_confirmation'] = 'required_with:password|string|min:8';

            if (Auth::user()->id === $user->id) {
                $rules['old_password'] = 'required|string';
            }
        }

        // 4. Valide la requête avec les règles définies
        $validatedData = $request->validate($rules);

        // 5. Vérification de l'ancien mot de passe pour les utilisateurs qui modifient le leur
        // et vérification que le nouveau mot de passe n'est pas le même que l'ancien
        if (Auth::user()->id === $user->id && $request->filled('password')) {
            // Vérification de l'ancien mot de passe
            if (!Hash::check($request->old_password, $user->password)) {
                throw ValidationException::withMessages([
                    'old_password' => ['L\'ancien mot de passe est incorrect.'],
                ]);
            }

            // NOUVEAU: Vérification que le nouveau mot de passe n'est pas le même que l'ancien
            if (Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'password' => ['Le nouveau mot de passe ne peut pas être identique à l\'ancien.'],
                ]);
            }
        }

        // 6. Mettre à jour les attributs de l'utilisateur avec les données validées
        $user->fill(array_diff_key($validatedData, array_flip(['password', 'password_confirmation', 'old_password'])));

        // 7. Gérer le nouveau mot de passe séparément s'il a été fourni et validé
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        // 8. Sauvegarde les modifications
        $user->save();

        // Retourne une réponse JSON avec l'utilisateur mis à jour
        return response()->json([
            'message' => 'Utilisateur mis à jour avec succès.',
            'data' => $user
        ]);
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
    // 1. Autorisation de l'utilisateur
    if (Auth::user()->id !== $user->id && Auth::user()->role !== 'admin') {
        return response()->json([
            'message' => 'Vous n\'êtes pas autorisé à modifier ce profil.'
        ], 403);
    }

    // 2. Validation des données de la requête
    $rules = [
        'password' => 'required|string|min:8|confirmed', // Le champ est 'password' et non 'new_password'
    ];

    // L'ancien mot de passe est requis seulement pour l'utilisateur qui modifie son propre profil
    if (Auth::user()->id === $user->id) {
        $rules['old_password'] = 'required|string'; // Le champ est 'old_password'
    }

    $request->validate($rules);

    // 3. Vérification du mot de passe actuel pour l'utilisateur
    // Cette vérification ne s'applique qu'à l'utilisateur qui modifie son propre mot de passe
    if (Auth::user()->id === $user->id) {
        if (!Hash::check($request->old_password, $user->password)) {
            throw ValidationException::withMessages([
                'old_password' => ['L\'ancien mot de passe est incorrect.'],
            ]);
        }
    }

    // 4. Mise à jour du mot de passe
    $user->password = Hash::make($request->password);
    $user->save();

    return response()->json([
        'message' => 'Le mot de passe a été mis à jour avec succès.'
    ], 200);
}

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('lastlogin')->nullable()->after('remember_token'); // Ou après une autre colonne pertinente
        });
    }
}
