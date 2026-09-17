<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Compte rendu - Session #{{ $session->id }}</title>
    <style>
        /* ---------- Styles généraux du compte rendu ANAGRASC ---------- */
        body   { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; margin: 0; }
        .entete p { margin: 2px 0; }

        h1 { font-size: 20px; margin: 0 0 4px; color: #1a3d5c; }
        h2 { font-size: 14px; margin: 22px 0 8px; color: #1a3d5c; border-bottom: 2px solid #1a3d5c; padding-bottom: 4px; }
        h3 { font-size: 13px; margin: 14px 0 4px; }

        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #999; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #eef3f7; font-weight: bold; }

        .carte { border: 1px solid #ccc; border-radius: 6px; padding: 10px 14px; margin-top: 10px; }
        .ligne { margin: 3px 0; }
        .libelle { color: #555; display: inline-block; width: 210px; }
        .valeur  { font-weight: bold; }

        .encadre { border: 2px solid #1a3d5c; padding: 10px; margin-top: 12px; background: #f8fbfd; }
        .gagnant { color: #0a7d33; }
        .muted   { color: #777; }
        .centre  { text-align: center; }
        .droite  { text-align: right; }
        .footer  { margin-top: 30px; font-size: 10px; color: #888; text-align: center; }
    </style>
</head>
<body>

    <!-- ---------- En-tête ---------- -->
    <div class="entete">
        <h1>ANAGRASC - Compte rendu d'enchère</h1>
        <p>Session n° <strong>#{{ $session->id }}</strong> - généré le {{ now()->format('d/m/Y à H:i') }}</p>
    </div>

    <!-- ---------- Informations générales ---------- -->
    <h2>1. Informations générales</h2>
    <div class="carte">
        <p class="ligne"><span class="libelle">Bien mis en vente :</span> <span class="valeur">{{ $bien->titre }}</span></p>
        <p class="ligne"><span class="libelle">Catégorie :</span> {{ $bien->categorie->libelle ?? '-' }}</p>
        <p class="ligne"><span class="libelle">Description :</span> {{ $bien->description }}</p>
        <p class="ligne"><span class="libelle">Statut de la session :</span> <span class="valeur">{{ $statutLibelle }}</span></p>
        <p class="ligne"><span class="libelle">Date de début :</span> {{ $session->date_debut?->format('d/m/Y H:i') ?? '-' }}</p>
        <p class="ligne"><span class="libelle">Date de fin :</span> {{ $session->date_fin?->format('d/m/Y H:i') ?? '-' }}</p>
        <p class="ligne"><span class="libelle">Commissaire-priseur :</span> {{ $commissaire->nom_complet ?? '-' }}</p>
        <p class="ligne"><span class="libelle">Superviseur :</span> {{ $superviseur->nom_complet ?? '-' }}</p>
        <p class="ligne"><span class="libelle">Mise à prix effective :</span> {{ number_format((float) $session->mise_a_prix_effective, 0, ',', ' ') }} FCFA</p>
        <p class="ligne"><span class="libelle">Surenchère minimale :</span> {{ number_format((float) $session->montant_min_surenchere, 0, ',', ' ') }} FCFA</p>
        {{-- Le prix de réserve est réputé confidentiel : il n'apparaît que dans
             le compte rendu interne destiné au commissaire / superviseur / admin. --}}
        <p class="ligne"><span class="libelle">Prix de réserve (confidentiel) :</span> {{ number_format((float) ($session->getAttributes()['prix_reserve'] ?? 0), 0, ',', ' ') }} FCFA</p>
    </div>

    <!-- ---------- Adjudication ---------- -->
    <h2>2. Adjudication</h2>
    <div class="encadre">
        @if ($offreGagnante)
            <p class="ligne"><span class="libelle">Enchère <strong>remportée</strong> par :</span>
               <span class="valeur gagnant">{{ $offreGagnante->id_anonyme }}</span></p>
            <p class="ligne"><span class="libelle">Montant final :</span>
               <span class="valeur gagnant">{{ number_format((float) $offreGagnante->montant, 0, ',', ' ') }} FCFA</span></p>
            <p class="ligne"><span class="libelle">Nombre d'offres :</span> {{ $offres->count() }}</p>
            <p class="ligne"><span class="libelle">Nombre de participants :</span> {{ $participants->count() }}</p>
        @else
            <p class="ligne"><strong>Aucune offre valable</strong> (prix de réserve non atteint ou aucune offre).</p>
            <p class="ligne muted">Le bien a été remis en vente.</p>
        @endif
    </div>

    <!-- ---------- Historique des offres ---------- -->
    <h2>3. Historique des offres</h2>
    <table>
        <thead>
            <tr>
                <th>Rang</th>
                <th>ID anonyme</th>
                <th>Montant</th>
                <th>Horodatage</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($offres as $offre)
            <tr>
                <td class="centre">{{ $offre->rang ?? '-' }}</td>
                <td>{{ $offre->id_anonyme }}</td>
                <td class="droite">{{ number_format((float) $offre->montant, 0, ',', ' ') }} FCFA</td>
                <td>{{ $offre->created_at?->format('d/m/Y H:i:s') ?? '-' }}</td>
                <td>
                    @if ($offre->est_gagnante)
                        <span class="gagnant">Gagnante</span>
                    @else
                        <span class="muted">Non retenue</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="centre muted">Aucune offre enregistrée.</td></tr>
            @endforelse
        </tbody>
    </table>

    <!-- ---------- Pied de page ---------- -->
    <div class="footer">
        Document généré automatiquement par la plateforme ANAGRASC. Ce compte rendu fait foi de la tenue de la séance.
    </div>

</body>
</html>