<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offre;
use App\Models\SessionEnchere;
use App\Models\Encherisseur;
use App\Services\WalletService;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\LogController;
class OffreController extends Controller
{
    public function __construct(
        private WalletService $walletService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Consultation publique
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/sessions/{session}/offres
     * Public – Polling toutes les 3 secondes.
     * Retourne l'historique des offres + la meilleure offre et le timer.
     */
    public function index(SessionEnchere $session)
    {
        return response()->json([
            'data' => [
                'statut'                     => $session->statut,
                'offres'                     => $session->offres()
                    ->select('montant', 'id_anonyme', 'created_at')
                    ->orderByDesc('montant')
                    ->limit(20)
                    ->get()
                    ->map(fn($o) => [
                        'montant'    => (float) $o->montant,
                        'id_anonyme' => $o->id_anonyme,
                        'heure'      => $o->created_at->format('H:i:s'),
                    ]),
                'meilleure_offre'            => (float) ($session->meilleure_offre?->montant ?? 0),
                'id_meilleur'                => $session->meilleure_offre?->id_anonyme ?? null,
                'secondes_restantes'         => $session->secondes_restantes,
                'mise_a_prix_effective'      => (float) $session->mise_a_prix_effective,
                'montant_min_surenchere'     => (float) $session->montant_min_surenchere,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Enchérisseur (connecté, valide)
    |--------------------------------------------------------------------------
    */

    /**
     * POST /api/sessions/{session}/offrir
     * auth:sanctum + role:encherisseur + encherisseur.valide
     * Placer une offre. Applique les règles :
     * - Session active
     * - Solde suffisant (caution)
     * - Vérification prix de réserve
     * - Surenchère minimum
     * - Génération de l'ID anonyme
     */
    public function store(Request $request, SessionEnchere $session)
    {
        // Vérifier que la session est bien en cours
        if (!$session->estActive()) {
            return response()->json(['message' => 'Les offres ne sont pas acceptées pour cette session.'], 422);
        }

        $user = $request->user();
        $encherisseur = $user->encherisseur;
        if (!$encherisseur) {
            return response()->json(['message' => 'Profil enchérisseur introuvable.'], 403);
        }

        $validated = $request->validate([
            'montant' => 'required|numeric|min:0',
        ]);
        $montant = (float) $validated['montant'];

        // Montant minimum = max(meilleure offre actuelle, mise à prix) + sur enchère minimum
        $meilleureActuelle = (float) ($session->meilleure_offre?->montant ?? 0);
        $miseAPrix         = (float) $session->mise_a_prix_effective;
        $surenchereMin     = (float) $session->montant_min_surenchere;
        $montantMinimum    = max($meilleureActuelle, $miseAPrix) + $surenchereMin;

        if ($montant < $montantMinimum) {
            return response()->json([
                'message' => "Montant minimum requis : {$montantMinimum} FCFA.",
            ], 422);
        }

        // Vérification du prix de réserve
        $prixReserve = (float) ($session->prix_reserve ?? 0);
        if ($prixReserve > 0 && $montant < $prixReserve) {
            $offreMax = (float) ($session->meilleure_offre?->montant ?? 0);
            if ($offreMax < $prixReserve) {
                return response()->json([
                    'message' => 'Le montant doit être supérieur ou égal au prix de réserve confidentiel.',
                ], 422);
            }
        }

        // Vérification du solde : caution requise
        $caution = $session->getCautionRequise();
        if (!$encherisseur->wallet || !$encherisseur->wallet->soldeSuffisant($caution)) {
            return response()->json([
                'message' => "Solde insuffisant. La caution requise est de {$caution} FCFA.",
            ], 422);
        }

        // Prélèvement de la caution (une seule fois par session)
        $offreExistante = Offre::where('session_id', $session->id)
            ->where('encherisseur_id', $encherisseur->id)
            ->exists();
        if (!$offreExistante) {
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

        // Génération de l'ID anonyme (seulement pour la première offre)
        $idAnonyme = $offreExistante
            ? Offre::where('session_id', $session->id)
                  ->where('encherisseur_id', $encherisseur->id)
                  ->value('id_anonyme')
            : Offre::genererIdAnonyme($session->id);

        // Créer l'offre
        $offre = Offre::create([
            'session_id'      => $session->id,
            'encherisseur_id' => $encherisseur->id,
            'montant'         => $montant,
            'id_anonyme'      => $idAnonyme,
            'est_gagnante'    => false,
        ]);

        //Log de l'offre placée
          LogController::log(
                'offre',
                "Offre de {$offre->montant} FCFA placée par {$offre->id_anonyme} sur la session #{$session->id}"
            );
        return response()->json([
            'message'    => 'Offre enregistrée.',
            'id_anonyme' => $idAnonyme,
            'montant'    => $montant,
        ], 201);
    }

    /**
     * GET /api/mes-participations
     * auth:sanctum + role:encherisseur + encherisseur.valide
     * Liste des sessions auxquelles l'enchérisseur a participé.
     */
    public function mesParticipations(Request $request)
    {
        $encherisseur = $request->user()->encherisseur;
        if (!$encherisseur) {
            return response()->json(['message' => 'Profil enchérisseur introuvable.'], 403);
        }

        $participations = Offre::with([
                'session' => fn($q) => $q->select('id', 'bien_id', 'date_fin', 'statut'),
                'session.bien' => fn($q) => $q->select('id', 'titre'),
            ])
            ->where('encherisseur_id', $encherisseur->id)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('session_id')
            ->map(fn($offres, $sessionId) => [
                'session_id'         => $sessionId,
                'bien'               => $offres->first()->session->bien->titre ?? '',
                'statut'             => $offres->first()->session->statut ?? '',
                'ma_meilleure_offre' => (float) $offres->max('montant'),
                'date_derniere'      => $offres->max('created_at')->toDateTimeString(),
            ])
            ->values();

        return response()->json(['data' => $participations]);
    }

    /**
     * GET /api/mes-offres-en-cours
     * auth:sanctum + role:encherisseur + encherisseur.valide
     * Sessions actives où l'enchérisseur a placé au moins une offre.
     */
    public function offresEnCours(Request $request)
    {
        $encherisseur = $request->user()->encherisseur;
        if (!$encherisseur) {
            return response()->json(['message' => 'Profil enchérisseur introuvable.'], 403);
        }

        $offresActives = Offre::with([
                'session' => fn($q) => $q->whereIn('statut', [
                    SessionEnchere::STATUT_EN_COURS,
                ])->select('id', 'bien_id', 'date_fin', 'mise_a_prix_effective', 'statut'),
                'session.bien' => fn($q) => $q->select('id', 'titre'),
            ])
            ->where('encherisseur_id', $encherisseur->id)
            ->whereHas('session', fn($q) => $q->where('statut', SessionEnchere::STATUT_EN_COURS))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('session_id')
            ->map(fn($offres, $sessionId) => [
                'session_id'           => $sessionId,
                'bien'                 => $offres->first()->session->bien->titre ?? '',
                'mise_a_prix'          => (float) ($offres->first()->session->mise_a_prix_effective ?? 0),
                'ma_meilleure_offre'   => (float) $offres->max('montant'),
                'secondes_restantes'   => $offres->first()->session->secondes_restantes,
                'id_anonyme'           => $offres->first()->id_anonyme,
            ])
            ->values();

        return response()->json(['data' => $offresActives]);
    }
}


