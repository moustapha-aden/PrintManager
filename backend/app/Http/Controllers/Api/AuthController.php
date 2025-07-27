<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash; // Import nécessaire pour Hash::make()
use App\Models\User; // Import du modèle User

class AuthController extends Controller
{
    /**
     * Gère la connexion des utilisateurs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis ne correspondent pas à nos enregistrements.'],
            ]);
        }

        $user = $request->user();
        $token = $user->createToken('auth_token')->plainTextToken;

        $userRole = $user->role ?? 'client';
        $userRoles = [];
        if (!empty($userRole)) {
            $userRoles[] = $userRole;
        }

        return response()->json([
            'message' => 'Connexion réussie',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $userRole,
                'roles' => array_values(array_unique($userRoles)),
                'company' => $user->company ?? null,
                'status' => $user->status ?? null,
                'statusDisplay' => $user->statusDisplay ?? null,
                'lastLogin' => $user->lastLogin ?? null,
                'requestsHandled' => $user->requestsHandled ?? null,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Gère l'inscription des nouveaux utilisateurs, par défaut en tant que 'client'.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed', // 'confirmed' nécessite un champ password_confirmation
            'company' => 'nullable|string|max:255', // Le champ compagnie est maintenant toujours attendu pour un client
        ]);

        // Attribuer le rôle 'client' par défaut
        $validatedData['role'] = 'client';
        $validatedData['roleDisplay'] = 'Client'; // Valeur par défaut pour l'affichage
        $validatedData['status'] = 'active'; // Statut par défaut
        $validatedData['statusDisplay'] = 'Actif'; // Statut d'affichage par défaut
        $validatedData['lastLogin'] = null; // Pas de dernière connexion à l'inscription
        $validatedData['requestsHandled'] = '0'; // Par défaut 0 pour un client

        // Hasher le mot de passe avant de le sauvegarder
        $validatedData['password'] = Hash::make($validatedData['password']);

        // Créer l'utilisateur
        $user = User::create($validatedData);

        return response()->json([
            'message' => 'Inscription réussie en tant que client. Vous pouvez maintenant vous connecter.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'roleDisplay' => $user->roleDisplay,
                'company' => $user->company,
                'status' => $user->status,
                'statusDisplay' => $user->statusDisplay,
                'requestsHandled' => $user->requestsHandled,
            ],
        ], 201); // Code 201 Created
    }

    /**
     * Gère la déconnexion des utilisateurs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnexion réussie']);
    }

    /**
     * Retourne les informations de l'utilisateur authentifié.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        $user = $request->user();
        $userRole = $user->role ?? 'client';
        $userRoles = [];
        if (!empty($userRole)) {
            $userRoles[] = $userRole;
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $userRole,
                'roles' => array_values(array_unique($userRoles)),
                'company' => $user->company ?? null,
                'status' => $user->status ?? null,
                'statusDisplay' => $user->statusDisplay ?? null,
                'lastLogin' => $user->lastLogin ?? null,
                'requestsHandled' => $user->requestsHandled ?? null,
            ]
        ]);
    }
}
