<!DOCTYPE html>
<html>
<head>
    <title>Vos identifiants de connexion</title>
    <!-- Inclure le script Tailwind CSS pour une mise en page moderne -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100 p-6 sm:p-10">
    <div class="max-w-xl mx-auto bg-white rounded-lg shadow-xl overflow-hidden">
        <!-- En-tête avec un logo et un titre -->
        <div class="bg-blue-600 text-white p-6 text-center">
            <h1 class="text-3xl font-bold">Bienvenue sur notre plateforme !</h1>
        </div>

        <div class="p-8 space-y-6">
            <!-- Message de bienvenue personnalisé -->
            <h2 class="text-2xl font-semibold text-gray-800">Bonjour {{ $user->name }},</h2>

            <p class="text-gray-700 leading-relaxed">
                Votre compte a été créé avec succès. Nous sommes ravis de vous accueillir !
            </p>

            <!-- Section pour les identifiants de connexion -->
            <div class="bg-blue-50 border-l-4 border-blue-400 text-blue-800 p-4 rounded-lg" role="alert">
                <p class="font-bold">Voici vos informations de connexion :</p>
                <div class="mt-4 space-y-2">
                    <p class="flex items-center">
                        <span class="font-medium mr-2">Email :</span>
                        <code class="bg-gray-200 px-2 py-1 rounded-md text-sm font-mono">{{ $user->email }}</code>
                    </p>
                    <p class="flex items-center">
                        <span class="font-medium mr-2">Mot de passe :</span>
                        <code class="bg-gray-200 px-2 py-1 rounded-md text-sm font-mono">{{ $password }}</code>
                    </p>
                </div>
            </div>

            <p class="text-gray-700 leading-relaxed">
                Vous pouvez vous connecter en utilisant les informations ci-dessus. Pour des raisons de sécurité, nous vous recommandons de changer votre mot de passe après votre première connexion.
            </p>

            <!-- Bouton d'appel à l'action (CTA) -->
            <div class="text-center pt-4">
                <a href="{{ url('/login') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-full transition duration-300 ease-in-out">
                    Se connecter
                </a>
            </div>

            <!-- Salutations -->
            <p class="text-gray-700 pt-6">
                Merci,<br>
                L'équipe de support
            </p>
        </div>

        <!-- Pied de page -->
        <div class="bg-gray-200 text-gray-600 text-sm text-center p-4">
            <p>Ceci est un e-mail automatisé. Veuillez ne pas y répondre.</p>
        </div>
    </div>
</body>
</html>
