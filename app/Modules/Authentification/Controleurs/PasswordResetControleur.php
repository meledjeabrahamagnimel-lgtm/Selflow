<?php

namespace App\Modules\Authentification\Controleurs;

use App\Http\Controllers\Controller;
use App\Modules\Authentification\Modeles\Utilisateur;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetControleur extends Controller
{
    /**
     * Afficher le formulaire de demande de réinitialisation.
     */
    public function afficherDemande(): View
    {
        return view('authentification::mot_de_passe_oublie');
    }

    /**
     * Traiter la demande de lien de réinitialisation.
     */
    public function envoyerLien(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:utilisateurs,email'],
        ], [
            'email.required' => 'L\'adresse e-mail est requise.',
            'email.email'    => 'L\'adresse e-mail n\'est pas valide.',
            'email.exists'   => 'Aucun compte n\'est associé à cette adresse e-mail.',
        ]);

        $email = $request->email;

        // Générer le jeton unique
        $token = Str::random(60);

        // Enregistrer le jeton en base
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token'      => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // URL de réinitialisation
        $lien = route('password.reset', ['token' => $token, 'email' => $email]);

        // Envoyer l'email (ou l'écrire dans les logs si SMTP non configuré)
        try {
            Mail::raw("Bonjour,\n\nVous recevez cet e-mail car nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.\n\nCliquez sur le lien ci-dessous pour réinitialiser votre mot de passe (ce lien expire dans 60 minutes) :\n\n" . $lien . "\n\nSi vous n'avez pas demandé cette réinitialisation, aucune action supplémentaire n'est requise.\n\nCordialement,\nL'équipe Selflow ERP", function ($message) use ($email) {
                $message->to($email)
                    ->subject('Réinitialisation de votre mot de passe - Selflow ERP');
            });
        } catch (\Exception $e) {
            // Loguer l'erreur mais continuer en écrivant l'URL dans la session pour faciliter le test local
            logger()->error("Erreur d'envoi de mail de réinitialisation: " . $e->getMessage() . " | Lien: " . $lien);
        }

        // Pour le développement local, on met le lien en session pour pouvoir l'utiliser facilement dans l'UI
        session()->flash('lien_developpement', $lien);

        return back()->with('succes', 'Un lien de réinitialisation vous a été envoyé par e-mail.');
    }

    /**
     * Afficher le formulaire de réinitialisation.
     */
    public function afficherReset(string $token, Request $request): View
    {
        $email = $request->query('email');
        return view('authentification::reinitialiser_mot_de_passe', compact('token', 'email'));
    }

    /**
     * Traiter le changement de mot de passe.
     */
    public function reinitialiser(Request $request): RedirectResponse
    {
        $request->validate([
            'token'                 => ['required', 'string'],
            'email'                 => ['required', 'email', 'exists:utilisateurs,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.required'        => 'L\'adresse e-mail est requise.',
            'email.email'           => 'L\'adresse e-mail n\'est pas valide.',
            'email.exists'          => 'Aucun compte n\'est associé à cette adresse e-mail.',
            'password.required'     => 'Le nouveau mot de passe est obligatoire.',
            'password.min'          => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed'    => 'Les deux mots de passe ne correspondent pas.',
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'email' => 'Jeton de réinitialisation invalide ou expiré.',
            ]);
        }

        // Vérifier l'expiration du jeton (60 minutes)
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            throw ValidationException::withMessages([
                'email' => 'Ce jeton de réinitialisation a expiré.',
            ]);
        }

        // Vérifier la correspondance du jeton
        if (! Hash::check($request->token, $record->token)) {
            throw ValidationException::withMessages([
                'email' => 'Jeton de réinitialisation invalide ou incorrect.',
            ]);
        }

        // Mettre à jour le mot de passe
        $utilisateur = Utilisateur::where('email', $request->email)->firstOrFail();
        $utilisateur->update([
            'password' => Hash::make($request->password),
        ]);

        // Supprimer le jeton
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return redirect()->route('connexion')->with('succes', 'Votre mot de passe a été modifié avec succès. Vous pouvez maintenant vous connecter.');
    }
}
