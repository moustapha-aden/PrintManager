<?php

namespace App\Http\Controllers\Api; // <-- TRÈS IMPORTANT : Ce namespace doit être exact

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // <-- Importez Auth
use Illuminate\Validation\ValidationException; // <-- Importez ValidationException
use App\Models\User; // <-- Importez votre modèle User

class AuthController extends Controller // <-- TRÈS IMPORTANT : Le nom de la classe doit être exact
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

        // Tente d'authentifier l'utilisateur avec les identifiants fournis
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis ne correspondent pas à nos enregistrements.'],
            ]);
        }

        // Récupère l'utilisateur authentifié
        $user = $request->user();

        // Crée un token Sanctum pour l'utilisateur
        // 'auth_token' est le nom du token, vous pouvez le personnaliser
        $token = $user->createToken('auth_token')->plainTextToken;

        //  Gestion des rôles
        // Nous allons utiliser la colonne 'role' de votre modèle User.
        // Si vous avez une logique de rôles plus complexe (par ex. via une table de pivot),
        // vous devrez adapter cette partie pour récupérer tous les rôles associés à l'utilisateur.
        $userRole = $user->role ?? 'client'; // Utilise le rôle de la colonne 'role', ou 'client' par défaut
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
                'role' => $userRole, // Rôle principal
                'roles' => array_values(array_unique($userRoles)), // Tous les rôles uniques (peut être le même que 'role' si un seul)
                // Ajoutez ici d'autres attributs de l'utilisateur si nécessaire pour le frontend
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
     * Gère la déconnexion des utilisateurs.
     * Invalide le token d'accès personnel actuellement utilisé.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Supprime le token actuel utilisé pour l'authentification
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie']);
    }

    /**
     * Retourne les informations de l'utilisateur authentifié.
     * Cette méthode est appelée par la route /api/user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        $user = $request->user();

        $userRole = $user->role ?? 'client'; // Rôle principal
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
                // Ajoutez ici d'autres attributs de l'utilisateur si nécessaire
                'company' => $user->company ?? null,
                'status' => $user->status ?? null,
                'statusDisplay' => $user->statusDisplay ?? null,
                'lastLogin' => $user->lastLogin ?? null,
                'requestsHandled' => $user->requestsHandled ?? null,
            ]
        ]);
    }
}
