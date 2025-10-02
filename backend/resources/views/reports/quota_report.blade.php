<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapport de Production Imprimantes</title>
    <style>
        body { font-family: 'Arial', sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; }
        .section { margin-bottom: 30px; border: 1px solid #ddd; padding: 10px; border-radius: 5px; }
        .section h2 { margin-top: 0; font-size: 16px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        .section h3 { margin-top: 15px; font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .highlight-red { color: red; font-weight: bold; }
        .highlight-green { color: green; font-weight: bold; }

        /* 👇 Forcer une nouvelle page après chaque imprimante */
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    @php
        use Illuminate\Support\Str;
        \Carbon\Carbon::setLocale('fr');
        setlocale(LC_TIME, 'fr_FR.utf8', 'fra');
    @endphp

    <div class="header">
        <h1>Rapport de Production</h1>
        <p>Période : {{ $startDate }} - {{ $endDate }}</p>
        @if($company)
            <p>Société : {{ $company->name }}</p>
        @endif
        <p>Généré le : {{ date('d/m/Y') }}</p>
    </div>

    @forelse($quotas as $quota)
        <div class="section {{ $loop->last ? '' : 'page-break' }}">
            <h2>
                Imprimante : {{ $quota->printer?->brand ?? 'N/A' }}
                {{ $quota->printer?->model ?? '' }}
                ({{ $quota->printer?->serial ?? '' }})
            </h2>

            <table>
                <tbody>
                    <tr>
                        <th>Département</th>
                        <td>{{ $quota->printer?->department?->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Marque</th>
                        <td>{{ $quota->printer?->brand ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Modèle</th>
                        <td>{{ $quota->printer?->model ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Numéro de série</th>
                        <td>{{ $quota->printer?->serial ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Nbr copie & imprimantes</th>
                        <td>{{ $quota->printer?->total_quota_pages ?? 'N/A' }}</td>
                    </tr>



                    @if(Str::contains($quota->printer?->model ?? '', 'B'))
                        <tr>
                            <th>Quota en B&N</th>
                            <td>
                                @if(($quota->printer?->company?->quota_monthly ?? 0) > 0)
                                    100 %  ({{ ($quota->printer->company->quota_monthly) }} copies)
                                @elseif(($quota->printer?->department?->quota_monthly ?? 0) > 0)
                                    100 %  ({{ ($quota->printer->department->quota_monthly) }} copies)
                                @else
                                    N/A
                                @endif
                            </td>
                        </tr>
                    @else
                        <tr>
                            <th>Quota en B&N</th>
                            <td>
                                @if(($quota->printer?->company?->quota_monthly ?? 0) > 0)
                                    {{ $quota->printer->company->quota_BW ?? 0 }} %  ({{ ($quota->printer->company->quota_monthly * ($quota->printer->company->quota_BW ?? 0))/100 }} copies)
                                @elseif(($quota->printer?->department?->quota_monthly ?? 0) > 0)
                                    {{ $quota->printer->company->quota_BW ?? 0 }} %  ({{ ($quota->printer->department->quota_monthly * ($quota->printer->company->quota_BW ?? 0))/100 }} copies)
                                @else
                                    N/A
                                @endif
                            </td>
                        </tr>
                        <tr>
                        <th>Quota  Couleur</th>
                        <td>
                            @if(($quota->printer?->company?->quota_monthly ?? 0) > 0)
                               {{ $quota->printer->company->quota_Color }} %  ({{ ($quota->printer->company->quota_monthly  * $quota->printer->company->quota_Color)/100 }} copies)
                            @elseif(($quota->printer?->department?->quota_monthly ?? 0) > 0)
                                {{ $quota->printer->company->quota_Color }} % {{ ($quota->printer->department->quota_monthly  *$quota->printer->company->quota_Color)/100 }} copies
                            @else
                                N/A
                            @endif
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>

            <h3>Relevés du Mois {{ \Carbon\Carbon::parse($quota->mois)->translatedFormat('F Y') }}</h3>
            <table>
                <tbody>
                    <tr>
                        <th>Nb imprimantes et copies en N&B</th>
                        <td>{{ $quota->monthly_quota_bw }}</td>
                    </tr>
                    @if(Str::contains($quota->printer?->model ?? '', 'C'))
                        <tr>
                            <th>Nb imprimantes et copies en Couleur</th>
                            <td>{{ $quota->monthly_quota_color }}</td>
                        </tr>
                    @endif

                    <tr>
                        <th>Nb imprimantes et copies Grand format en N&B</th>
                        <td>{{ $quota->monthly_quota_bw_large }}</td>
                    </tr>
                    @if(Str::contains($quota->printer?->model ?? '', 'C'))
                        <tr>
                            <th>Nb imprimantes et copies Grand format en Couleur</th>
                            <td>{{ $quota->monthly_quota_color_large }}</td>
                        </tr>
                    @endif
                    <tr>
                        <th>Total d'imprimantes et copies</th>
                        <td>{{ $quota->total_quota }}</td>
                    </tr>
                    <tr>
                        <th>Dépassement N&B</th>
                        <td class="{{ $quota->depassementBW > 0 ? 'highlight-red' : 'highlight-green' }}">
                            {{ $quota->depassementBW ?? 0 }}
                        </td>
                    </tr>
                    <tr>
                        <th>Dépassement Couleur</th>
                        <td class="{{ $quota->depassementColor > 0 ? 'highlight-red' : 'highlight-green' }}">
                            {{ $quota->depassementColor ?? 0 }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    @empty
        <p>Aucun quota trouvé pour cette période.</p>
    @endforelse
</body>
</html>
