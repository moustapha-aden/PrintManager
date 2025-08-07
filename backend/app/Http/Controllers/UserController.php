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


    $user = User::create($validated);

    // 3. Envoi de l'e-mail avec les informations de connexion
    Mail::to($user->email)->send(new UserCredentialsMail($user, $plainPassword));

    return response()->json([
        'message' => 'Utilisateur créé avec succès.',
        'data' => $user
    ], 201);
    }

    // Met à jour un utilisateur
    public function update(Request $request, User $user)
    {
        // Définition des règles de validation
        $rules = [
            'name' => 'sometimes|required|string|max:255', // 'sometimes' pour permettre les mises à jour partielles
            // Règle unique pour l'email, mais ignore l'email de l'utilisateur actuel
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'role' => 'sometimes|required|string|in:admin,client,technicien',
            'company_id' => 'nullable|exists:companies,id', // Peut être nul, doit exister
            'department_id' => 'nullable|exists:departments,id', // Peut être nul, doit exister
            'status' => 'sometimes|required|string|in:active,inactive',
            'lastLogin' => 'nullable|date', // 'nullable' si ce champ peut être vide
            'requestsHandled' => 'nullable|integer', // Supposons que c'est un entier
            // Le champ 'password' n'est pas requis par défaut pour la mise à jour.
            // Il sera traité uniquement si le frontend envoie un nouveau mot de passe.
        ];

        // Si un nouveau mot de passe est fourni, ajoutez les règles de validation spécifiques au mot de passe.
        // Puisque l'administrateur ne voit pas l'ancien mot de passe, nous ne validons pas 'old_password'.
        // Si un mot de passe est envoyé, il est considéré comme le nouveau mot de passe.
        if ($request->has('password') && !empty($request->password)) {
            $rules['password'] = 'string|min:8'; // Pas 'required' car il est déjà dans le 'if', pas 'confirmed' car le frontend n'envoie pas 'password_confirmation' pour la modification
        }

        // Valide la requête avec les règles définies
        $validatedData = $request->validate($rules);

        // Mettre à jour les attributs de l'utilisateur avec les données validées
        // La méthode fill() est plus propre pour les mises à jour massives
        $user->fill($validatedData);

        // Gérer le mot de passe séparément s'il a été fourni dans la requête
        if ($request->has('password') && !empty($request->password)) {
            $user->password = Hash::make($request->password);
        }

        // Sauvegarde les modifications
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

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('lastlogin')->nullable()->after('remember_token'); // Ou après une autre colonne pertinente
        });
    }
}
