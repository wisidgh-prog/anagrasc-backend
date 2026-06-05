<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppLog;
use App\Models\Offre;
use App\Models\SessionEnchere;
use App\Services\WalletService;
use Illuminate\Http\Request;

class OffreController extends Controller
{
    // Le WalletService est injecté automatiquement par Laravel (injection de dépendances)
    // On en a besoin pour prélever la caution lors de la première offre
    public function __construct(private WalletService $walletService) {}

    /**
     * GET /api/sessions/{session}/offres
     * Public – Retourne les offres d'une session pour le polling Angular (toutes les 3 secondes).
     */
    public function index(SessionEnchere $session)
    {
        // Récupérer les 20 meilleures offres en masquant l'identité des enchérisseurs
        $offres = $session->offres()
            ->select('montant', 'id_anonyme', 'created_at')
            ->orderByDesc('montant')
            ->limit(20)
            ->get();

        // Formater les offres pour l'affichage
        $offresFormatees = [];
        foreach ($offres as $offre) {
            $offresFormatees[] = [
                'montant'    => (float) $offre->montant,
                'id_anonyme' => $offre->id_anonyme,
                'heure'      => $offre->created_at->format('H:i:s'),
            ];
        }

        return response()->json([
            'data' => [
                'statut'                 => $session->statut,
                'offres'                 => $offresFormatees,
                'meilleure_offre'        => (float) ($session->meilleure_offre?->montant ?? 0),
                'id_meilleur'            => $session->meilleure_offre?->id_anonyme ?? null,
                'secondes_restantes'     => $session->secondes_restantes,
                'mise_a_prix_effective'  => (float) $session->mise_a_prix_effective,
                'montant_min_surenchere' => (float) $session->montant_min_surenchere,
            ],
        ]);
    }

    /**
     * POST /api/sessions/{session}/offrir
     * auth:sanctum + role:encherisseur (validé)
     * Placer une offre sur une session active (CDC Scénario 3).
     */
    public function store(Request $request, SessionEnchere $session)
    {
        // 1. Vérifier que la session est bien active
        if (!$session->estActive()) {
            return response()->json(['message' => 'Les offres ne sont pas acceptées pour cette session.'], 422);
        }

        // 2. Récupérer le profil enchérisseur de l'utilisateur connecté
        $user         = $request->user();
        $encherisseur = $user->encherisseur;

        if (!$encherisseur) {
            return response()->json(['message' => 'Profil enchérisseur introuvable.'], 403);
        }

        // 3. Valider le montant envoyé
        $request->validate([
            'montant' => 'required|numeric|min:0',
        ]);

        $montant = (float) $request->montant;

        // 4. Calculer le montant minimum accepté
        // Règle : max(meilleure offre actuelle, mise à prix) + surenchère minimum
        $meilleureActuelle = (float) ($session->meilleure_offre?->montant ?? 0);
        $miseAPrix         = (float) $session->mise_a_prix_effective;
        $surenchereMin     = (float) $session->montant_min_surenchere;
        $montantMinimum    = max($meilleureActuelle, $miseAPrix) + $surenchereMin;

        if ($montant < $montantMinimum) {
            return response()->json([
                'message' => "Montant minimum requis : {$montantMinimum} FCFA.",
            ], 422);
        }

        // 5. Vérifier que le solde du wallet est suffisant pour payer la caution
        $caution = $session->getCautionRequise();

        if (!$encherisseur->wallet || !$encherisseur->wallet->soldeSuffisant($caution)) {
            return response()->json([
                'message' => "Solde insuffisant. La caution requise est de {$caution} FCFA.",
            ], 422);
        }

        // 6. Prélever la caution seulement si c'est la première offre de cet enchérisseur sur cette session
        $dejaParticipe = Offre::where('session_id', $session->id)
            ->where('encherisseur_id', $encherisseur->id)
            ->exists();

        if (!$dejaParticipe) {
            $debite = $this->walletService->debiter(
                $encherisseur,
                $caution,
                'prelevement_caution',
                $session->id
            );

            if (!$debite) {
                return response()->json(['message' => 'Erreur lors du prélèvement de la caution.'], 500);
            }
        }

        // 7. Récupérer ou générer l'ID anonyme
        // Chaque enchérisseur garde le même ID anonyme pour toute la session (ex: ENCH-001)
        if ($dejaParticipe) {
            // Récupérer l'ID anonyme déjà attribué
            $idAnonyme = Offre::where('session_id', $session->id)
                ->where('encherisseur_id', $encherisseur->id)
                ->value('id_anonyme');
        } else {
            // Générer un nouvel ID anonyme séquentiel
            $idAnonyme = Offre::genererIdAnonyme($session->id);
        }

        // 8. Créer l'offre
        $offre = Offre::create([
            'session_id'      => $session->id,
            'encherisseur_id' => $encherisseur->id,
            'montant'         => $montant,
            'id_anonyme'      => $idAnonyme,
            'est_gagnante'    => false,
        ]);

        // Enregistrer l'action dans les logs
        AppLog::create([
            'user_id'     => $user->id,
            'action'      => 'offre',
            'description' => "Offre de {$offre->montant} FCFA placée par {$offre->id_anonyme} sur la session #{$session->id}",
            'horodatage'  => now(),
        ]);

        return response()->json([
            'message'    => 'Offre enregistrée.',
            'id_anonyme' => $idAnonyme,
            'montant'    => $montant,
        ], 201);
    }

    /**
     * GET /api/mes-participations
     * auth:sanctum + role:encherisseur (validé)
     * Historique de toutes les sessions auxquelles l'enchérisseur a participé.
     */
    public function mesParticipations(Request $request)
    {
        $encherisseur = $request->user()->encherisseur;

        if (!$encherisseur) {
            return response()->json(['message' => 'Profil enchérisseur introuvable.'], 403);
        }

        // Récupérer toutes les offres de cet enchérisseur avec les infos de session et de bien
        $toutesLesOffres = Offre::with([
                'session:id,bien_id,date_fin,statut',
                'session.bien:id,titre',
            ])
            ->where('encherisseur_id', $encherisseur->id)
            ->orderByDesc('created_at')
            ->get();

        // Regrouper par session pour n'afficher qu'une ligne par session
        $participations = [];
        $sessionsVues   = [];

        foreach ($toutesLesOffres as $offre) {
            $sessionId = $offre->session_id;

            // On n'ajoute chaque session qu'une seule fois
            if (!in_array($sessionId, $sessionsVues)) {
                $sessionsVues[] = $sessionId;

                // Trouver la meilleure offre de cet enchérisseur pour cette session
                $meilleureOffre = $toutesLesOffres
                    ->where('session_id', $sessionId)
                    ->max('montant');

                $participations[] = [
                    'session_id'         => $sessionId,
                    'bien'               => $offre->session->bien->titre ?? '',
                    'statut'             => $offre->session->statut ?? '',
                    'ma_meilleure_offre' => (float) $meilleureOffre,
                ];
            }
        }

        return response()->json(['data' => $participations]);
    }

    /**
     * GET /api/mes-offres-en-cours
     * auth:sanctum + role:encherisseur (validé)
     * Sessions actuellement en cours où l'enchérisseur a placé au moins une offre.
     */
    public function offresEnCours(Request $request)
    {
        $encherisseur = $request->user()->encherisseur;

        if (!$encherisseur) {
            return response()->json(['message' => 'Profil enchérisseur introuvable.'], 403);
        }

        // Récupérer les offres de cet enchérisseur dans des sessions actives
        $offresActives = Offre::with([
                'session:id,bien_id,date_fin,mise_a_prix_effective,statut',
                'session.bien:id,titre',
            ])
            ->where('encherisseur_id', $encherisseur->id)
            ->whereHas('session', function ($q) {
                $q->where('statut', SessionEnchere::STATUT_EN_COURS);
            })
            ->orderByDesc('created_at')
            ->get();

        // Regrouper par session
        $resultat     = [];
        $sessionsVues = [];

        foreach ($offresActives as $offre) {
            $sessionId = $offre->session_id;

            if (!in_array($sessionId, $sessionsVues)) {
                $sessionsVues[] = $sessionId;

                $meilleureOffre = $offresActives
                    ->where('session_id', $sessionId)
                    ->max('montant');

                $resultat[] = [
                    'session_id'           => $sessionId,
                    'bien'                 => $offre->session->bien->titre ?? '',
                    'mise_a_prix'          => (float) ($offre->session->mise_a_prix_effective ?? 0),
                    'ma_meilleure_offre'   => (float) $meilleureOffre,
                    'secondes_restantes'   => $offre->session->secondes_restantes,
                    'id_anonyme'           => $offre->id_anonyme,
                ];
            }
        }

        return response()->json(['data' => $resultat]);
    }
}
