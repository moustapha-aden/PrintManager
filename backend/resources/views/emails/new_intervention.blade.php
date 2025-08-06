<!DOCTYPE html>
<html>
<head>
    <title>Nouvelle Demande d'Intervention</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9; }
        h1 { color: #0056b3; }
        ul { list-style: none; padding: 0; }
        ul li { margin-bottom: 8px; }
        strong { color: #555; }
        .footer { margin-top: 20px; font-size: 0.9em; color: #777; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Une nouvelle demande d'intervention a été soumise.</h1>
        <p>Détails de l'intervention :</p>
        <ul>
            <li><strong>N° Demande:</strong> {{ $intervention->numero_demande ?? $intervention->id }}</li>
            <li><strong>Description:</strong> {{ $intervention->description }}</li>
            <li><strong>Type:</strong> {{ $intervention->intervention_type }}</li>
            <li><strong>Priorité:</strong> {{ $intervention->priority }}</li>
            <li><strong>Statut:</strong> {{ $intervention->status }}</li>
            <li><strong>Client:</strong> {{ $intervention->client->name ?? 'N/A' }}</li>
            <li><strong>Imprimante:</strong> {{ $intervention->printer->brand ?? 'N/A' }} {{ $intervention->printer->model ?? 'N/A' }}</li>
            <li><strong>Département ({{ $intervention->printer->brand ?? 'N/A' }} {{ $intervention->printer->model ?? 'N/A'}}):</strong> {{ $intervention->printer->department->name ?? 'N/A' }}</li> {{-- Correction ici pour accéder au nom du département de l'imprimante --}}
            <li><strong>Société:</strong> {{ $intervention->printer->department->company->name ?? 'N/A' }}</li>
            <li><strong>Date d'intervention:</strong> {{ \Carbon\Carbon::parse($intervention->start_date)}}</li>
            {{-- Ajoutez d'autres détails si nécessaire --}}
        </ul>
        <p>Veuillez vous connecter à l'application pour plus de détails et pour assigner ou traiter cette intervention.</p>
        <div class="footer">
            Ceci est un e-mail automatique, veuillez ne pas y répondre.
        </div>
    </div>
</body>
</html>
