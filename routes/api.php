<?php
use App\Http\Controllers\Api\SessionEnchereController;
use App\Http\Controllers\Api\BienController;
use App\Http\Controllers\Api\OffreController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\CategorieController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\PieceController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\ConfigurationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\MailTestController;



//ROUTE PROTÉGÉES DE CONNEXION ET DECONNECTION PAR AUTH:SANCTUM
 Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    // Paramètres non sensibles utiles aux formulaires (ex. surenchère minimum par défaut)
    Route::get('/configurations-publiques', [ConfigurationController::class, 'publiques']);
    // Compte rendu PDF d'une session terminée (commissaire, superviseur affecté ou admin)
    Route::get('/sessions/{session}/compte-rendu', [SessionEnchereController::class, 'compteRendu']);
 });
 /*
    |--------------------------------------------------------------------------
    | PUBLIQUE
    |--------------------------------------------------------------------------
    */
//Routes pour la connexion et l'inscription
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
// Routes pour les biens
Route::get('/biens', [BienController::class, 'index']);
Route::get('/biens/{bien}', [BienController::class, 'show']);
// Routes pour les enchères
Route::get('/ventes-passees', [SessionEnchereController::class, 'historique']);
Route::get('/sessions/{session}', [SessionEnchereController::class, 'show']);
// Route pour les offres d'une session (polling)
Route::get('/sessions/{session}/offres', [OffreController::class, 'index']);

// Route pour les catégories
Route::get('/categories', [CategorieController::class, 'index']);
/*
    |--------------------------------------------------------------------------
    | ENCHERISSEUR
    |--------------------------------------------------------------------------
    */

// Enchérisseur valide
Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckRole::class . ':encherisseur', \App\Http\Middleware\CheckEncherisseurValide::class])->group(function () {
// Routes pour le portefeuille
    Route::post('/sessions/{session}/offrir', [OffreController::class, 'store']);
    Route::get('/mes-participations', [OffreController::class, 'mesParticipations']);
    Route::get('/mes-offres-en-cours', [OffreController::class, 'offresEnCours']);

//Route portefeuille (wallet)

Route::get('/wallet', [WalletController::class, 'show']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
    Route::post('/wallet/deposer', [WalletController::class, 'deposer']);
    Route::get('/mes-cautions', [WalletController::class, 'cautions']);

   });



 /*
    |--------------------------------------------------------------------------
    | COMMISSAIRE-PRISEUR
    |--------------------------------------------------------------------------
    */

//ROUTES POUR LES BIENS
Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckRole::class . ':commissaire_priseur'])->group(function () {
    Route::get('/commissaire/biens', [BienController::class, 'mesBiens']);
    Route::post('/biens', [BienController::class, 'store']);
    Route::put('/biens/{bien}', [BienController::class, 'update']);
    Route::delete('/biens/{bien}', [BienController::class, 'destroy']);
    Route::post('/biens/{bien}/photos', [BienController::class, 'ajouterPhotos']);
//ROUTES POUR LES SESSIONS D'ENCHÈRES
    Route::get('/commissaire/sessions', [SessionEnchereController::class, 'indexCommissaire']);
    Route::post('/sessions', [SessionEnchereController::class, 'store']);
    Route::put('/sessions/{session}', [SessionEnchereController::class, 'update']);
    Route::post('/sessions/{session}/interrompre', [SessionEnchereController::class, 'interrompre']);
    Route::post('/sessions/{session}/reprendre', [SessionEnchereController::class, 'reprendre']);
    Route::get('/superviseurs', [AdminController::class, 'superviseurs']);
// Routes pour les incidents signalés
    Route::get('/incidents', [IncidentController::class, 'index']);
    Route::post('/incidents/{incident}/decision', [IncidentController::class, 'decision']);
//route pour le dashboard commissaire
    Route::get('/commissaire/dashboard', [DashboardController::class, 'commissaire']);


});

 /*
    |--------------------------------------------------------------------------
    | SUPERVISEUR
    |--------------------------------------------------------------------------
    */

// Routes (consultation et supervision en temps réel)
Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckRole::class . ':superviseur'])->group(function () {
    Route::get('/superviseur/sessions', [SessionEnchereController::class, 'indexSuperviseur']);
    Route::get('/superviseur/sessions/{session}', [SessionEnchereController::class, 'superviser']);
// Route pour signaler un incident
    Route::post('/incidents', [IncidentController::class, 'store']);

    });

    /*
    |--------------------------------------------------------------------------
    | ADMINISTRATEUR
    |--------------------------------------------------------------------------
    */


 Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckRole::class . ':administrateur'])->group(function () {
// Routes pour la gestion des utilisateurs
    Route::get('/utilisateurs', [AdminController::class, 'utilisateurs']);
    Route::post('/utilisateurs/{user}/valider', [AdminController::class, 'validerCompte']);
    Route::post('/utilisateurs/{user}/rejeter', [AdminController::class, 'rejeterCompte']);
    Route::post('/utilisateurs/{user}/suspendre', [AdminController::class, 'suspendreCompte']);
    Route::post('/utilisateurs/interne', [AdminController::class, 'creerCompteInterne']);
//Route pour le controlle des pieces
    Route::get('/pieces/{user}', [PieceController::class, 'show']);
    Route::get('/logs', [LogController::class, 'index']);
    Route::get('/logs/export', [LogController::class, 'export']);
//Routes pour les configurations
    Route::get('/configurations', [ConfigurationController::class, 'index']);
    Route::put('/configurations/{configuration}', [ConfigurationController::class, 'update']);
// Route de vérification SMTP (envoi d'UN SEUL email)
    Route::post('/mail-test', [MailTestController::class, 'envoyer']);
// Route pour le dashboard admin
    Route::get('/admin/dashboard', [DashboardController::class, 'admin']);
// Routes pour la gestion des rôles et permissions
    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::put('/roles/{role}', [RoleController::class, 'update']);
    Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
    Route::post('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
//Route pour voir les pieces
Route::get('/utilisateurs/{user}/piece/recto', [PieceController::class, 'afficherRecto']);
Route::get('/utilisateurs/{user}/piece/verso', [PieceController::class, 'afficherVerso']);
//Route pour la validation de bien
 Route::get('admin/biens', [BienController::class, 'indexAdmin']);
Route::post('/biens/{bien}/valider', [BienController::class, 'valider']);
Route::post('/biens/{bien}/rejeter', [BienController::class, 'rejeter']);
});