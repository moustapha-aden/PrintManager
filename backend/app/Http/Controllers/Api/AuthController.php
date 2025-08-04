<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon; // Import de la classe Carbon

class AuthController extends Controller
{
    /**
     * Gère la connexion des utilisateurs et met à jour leur dernière connexion.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis ne correspondent pas à nos enregistrements.'],
            ]);
        }

        $user = $request->user();

        // Mettre à jour la date de dernière connexion
        $user->lastlogin = now();
        $user->save();

        // Charger la relation company si elle existe
        $user->load('company');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 200);
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
            'company_id' => 'required|exists:companies,id', // Utiliser l'ID de la compagnie
        ]);

        // Attribuer le rôle 'client' et le statut 'active' par défaut
        $validatedData['role'] = 'client';
        $validatedData['status'] = 'active';

        // Hasher le mot de passe avant de le sauvegarder
        $validatedData['password'] = Hash::make($validatedData['password']);

        // Créer l'utilisateur
        $user = User::create($validatedData);

        // Charger la relation company pour la réponse
        $user->load('company');

        return response()->json([
            'message' => 'Inscription réussie en tant que client. Vous pouvez maintenant vous connecter.',
            'user' => $user,
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
        // Charger la relation company si elle existe
        $user->load('company');

        return response()->json([
            'user' => $user
        ]);
    }
}
