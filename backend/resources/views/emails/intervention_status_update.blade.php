<!DOCTYPE html>
<html>
<head>
    <title>Mise à jour de votre Demande d'Intervention</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9; }
        h1 { color: #0056b3; }
        ul { list-style: none; padding: 0; }
        ul li { margin-bottom: 8px; }
        strong { color: #555; }
        .footer { margin-top: 20px; font-size: 0.9em; color: #777; text-align: center; }
        .status-update {
            background-color: #e0f7fa; /* Light blue */
            padding: 10px;
            border-left: 5px solid #00bcd4; /* Cyan */
            margin-bottom: 15px;
        }
        .solution {
            background-color: #f0f4c3; /* Light yellow */
            padding: 10px;
            border-left: 5px solid #cddc39; /* Lime */
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Mise à jour de votre demande d'intervention</h1>

        <div class="status-update">
            <p>La demande d'intervention numéro <strong>{{ $intervention->numero_demande ?? $intervention->id }}</strong> a été mise à jour !</p>
            <p>Son nouveau statut est : <strong>{{ $intervention->status }}</strong></p>
        </div>

        @if ($intervention->status === 'En Cours' && $oldStatus !== 'En Cours')
            <p>Un technicien a été assigné à votre demande. Votre intervention est désormais **en cours** de traitement.</p>
            @if ($intervention->date_previsionnelle)
                <p>Date prévisionnelle : **{{ \Carbon\Carbon::parse($intervention->date_previsionnelle)->format('d/m/Y H:i') }}**</p>
            @endif
            @if ($intervention->technician)
                <p>Technicien assigné : {{ $intervention->technician->name }}</p>
            @endif
        @elseif ($intervention->status === 'Terminée')
            <p>Votre demande d'intervention a été marquée comme **terminée** le {{ \Carbon\Carbon::parse($intervention->end_date)->format('d/m/Y H:i') }}.</p>
            @if ($intervention->solution)
                <div class="solution">
                    <p><strong>Solution apportée :</strong></p>
                    <p>{{ $intervention->solution }}</p>
                </div>
            @endif
        @elseif ($intervention->status === 'Annulée')
            <p>Votre demande d'intervention a été **annulée**.</p>
            @if ($intervention->solution)
                <div class="solution">
                    <p><strong>Raison de l'annulation :</strong></p>
                    <p>{{ $intervention->solution }}</p>
                </div>
            @endif
        @else
            <p>Un changement de statut a été effectué pour votre intervention.</p>
        @endif

        <p>Détails de l'intervention :</p>
        <ul>
            <li><strong>N° Demande:</strong> {{ $intervention->numero_demande ?? $intervention->id }}</li>
            <li><strong>Description:</strong> {{ $intervention->description }}</li>
            <li><strong>Type:</strong> {{ $intervention->intervention_type }}</li>
            <li><strong>Priorité:</strong> {{ $intervention->priority }}</li>
            <li><strong>Statut actuel:</strong> {{ $intervention->status }}</li>
            <li><strong>Imprimante:</strong> {{ $intervention->printer->brand ?? 'N/A' }} {{ $intervention->printer->model ?? 'N/A' }}</li>
            <li><strong>Département ({{ $intervention->printer->brand ?? 'N/A' }} {{ $intervention->printer->model ?? 'N/A' }}):</strong> {{ $intervention->printer->department->name ?? 'N/A' }}</li>
            <li><strong>Société:</strong> {{ $intervention->printer->department->company->name ?? 'N/A' }}</li>
            <li><strong>Date de la demande:</strong> {{ \Carbon\Carbon::parse($intervention->start_date) }}</li>
        </ul>

        <p>Veuillez vous connecter à l'application pour plus de détails sur votre demande.</p>
        <div class="footer">
            Ceci est un e-mail automatique, veuillez ne pas y répondre.
        </div>
    </div>
</body>
</html>
