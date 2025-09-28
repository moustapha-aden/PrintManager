<!DOCTYPE html>
<html>
<head>
    <title>Rapport de Production Imprimante</title>
    <style>
        body { font-family: 'Arial', sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; }
        .section { margin-bottom: 20px; border: 1px solid #ddd; padding: 10px; border-radius: 5px; }
        .section h2 { margin-top: 0; font-size: 16px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .highlight-red { color: red; }
        .highlight-green { color: green; }
    </style>
</head>
<body>
    @php
        // 1. Définir la locale française pour Carbon
        \Carbon\Carbon::setLocale('fr');
        // 2. IMPORTANT : Définir la locale PHP LC_TIME pour les anciennes méthodes (sécurité)
        setlocale(LC_TIME, 'fr_FR.utf8', 'fra');
    @endphp

    <div class="header">
        <h1>Rapport de Quota Spécifique</h1>
        <p>Relevé du : {{ \Carbon\Carbon::parse($quota->date_prelevement)->format('d/m/Y') }}</p>
        <p>Imprimante : {{ $printer->brand }} {{ $printer->model }} ({{ $printer->serial }})</p>
        <p>Généré le : {{ date('d/m/Y') }}</p>
    </div>

    <div class="section">
        <h2>Détails de l'Imprimante</h2>
        <table>
            <tr>
                <th>Société</th>
                <td>{{ $printer->company->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Département</th>
                <td>{{ $printer->department->name ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Détails du Relevé du Mois <span>{{ \Carbon\Carbon::parse($quota->mois)->translatedFormat('F Y') }}</span></h2>
        <table>
            <tbody>
                <tr>
                    <th>Date de Prélèvement</th>
                    <td>{{ \Carbon\Carbon::parse($quota->date_prelevement)->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <th>Quota Mensuel N&B</th>
                    <td>{{ $quota->monthly_quota_bw }}</td>
                </tr>
                <tr>
                    <th>Quota Mensuel Couleur</th>
                    <td>{{ $quota->monthly_quota_color }}</td>
                </tr>
                <tr>
                    <th>Total Quota</th>
                    <td>{{ $quota->total_quota }}</td>
                </tr>
                <tr>
                    <th>Dépassement N&B</th>
                    <td class="{{ $quota->depassementBW > 0 ? 'highlight-red' : 'highlight-green' }}">{{ $quota->depassementBW }}</td>
                </tr>
                <tr>
                    <th>Dépassement Couleur</th>
                    <td class="{{ $quota->depassementColor > 0 ? 'highlight-red' : 'highlight-green' }}">{{ $quota->depassementColor }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
