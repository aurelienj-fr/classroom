# monStudio

Plateforme de réservation de cours pour studios de sport et de bien-être (yoga, pilates, fitness, danse…).
MVP sans paiement : recherche de studios, planning, réservation et annulation de séances, back-office gérant.

## Stack

| Couche | Choix |
|---|---|
| Langage | PHP 8.4 (`declare(strict_types=1)` partout) |
| Framework | Symfony 8.1 (Twig, Form, Validator, Security, Doctrine ORM 3) |
| Base de données | MySQL 8.4 |
| Front | Twig + Bootstrap 5.3 + Font Awesome 7 (servis localement via AssetMapper, sans build Node) |
| Qualité | PHPUnit 13, PHPStan niveau 8 (+ extensions Symfony/Doctrine), PHP-CS-Fixer (`@Symfony` + `@PSR12`) |

## Démarrage rapide (Docker)

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php bin/console doctrine:migrations:migrate -n
docker compose exec app php bin/console doctrine:fixtures:load -n
```

Application : <http://localhost:8080> (redirige vers `/fr`, `/en` ou `/es` selon la langue du navigateur)

### Comptes de démonstration

Mot de passe commun : `monstudio-demo`

| Rôle | E-mail |
|---|---|
| Administrateur | `admin@monstudio.test` |
| Gérant de studio | `gerant@monstudio.test` |
| Membre | `membre@monstudio.test` |

## Fonctionnalités

**Public / membres**
- Recherche de studios publiés par ville ou code postal et par discipline.
- Fiche studio avec planning sur 7 jours glissants (navigation jusqu'à 12 semaines).
- Fiche séance : places restantes, réservation, annulation.
- Espace « Mes réservations » : séances à venir et historique (avec présence).
- Inscription (membre ou gérant, 5 tentatives par heure et par IP), connexion avec « rester connecté », limitation des tentatives (5 / 15 min).

**Gérants (`ROLE_OWNER`)**
- Création et édition de studios (brouillon / publié, délai d'annulation).
- Cours (modèles : discipline, niveau, durée, capacité par défaut).
- Séances : création unitaire ou répétée chaque semaine (jusqu'à 26 semaines), modification (séances à venir et non annulées uniquement), annulation / rétablissement.
- Liste des inscrits et pointage des présences.

**Administrateurs (`ROLE_ADMIN`)**
- Gestion des disciplines (nom, icône Font Awesome).
- Accès à la gestion de tous les studios.

## Multilingue (FR, EN, ES)

- **URL préfixées et traduites** : `/fr/seances/12`, `/en/classes/12`, `/es/clases/12`. Les chemins sont déclarés par langue dans les `#[Route]` ; le préfixe est ajouté dans `config/routes.yaml`.
- **`/` redirige** vers la langue préférée du navigateur (`Accept-Language`), le français par défaut.
- **Sélecteur de langue** dans la barre de navigation (reste sur la page courante) et balises `hreflang` sur chaque page.
- **Traductions** dans `translations/` : `messages.{fr,en,es}.yaml` (interface, messages flash, erreurs métier) et `validators.{fr,en,es}.yaml` (validation). Les messages de Symfony (validation standard, sécurité) sont fournis par le framework.
- **Dates** formatées par le filtre Twig `local_date` (`App\Twig\LocalizedDateExtension`), sans dépendre de l'extension `intl`.
- **Disciplines** : nom français + traductions EN/ES saisies par l'administrateur (repli sur le français).
- **Contenus des gérants** (présentation du studio, nom et description des cours) : non traduits, affichés dans la langue de saisie.

Ajouter une langue : l'ajouter à `framework.enabled_locales` (`config/packages/translation.yaml`), au préfixe de `config/routes.yaml`, aux chemins des `#[Route]` et à `access_control`, puis créer ses fichiers de traduction et ses formats de date.

Vérifier les traductions manquantes :

```bash
docker compose exec app php bin/console debug:translation en --only-missing
```

## Règles métier

Centralisées dans `App\Booking\BookingManager` :

- Une séance est réservable si elle n'est ni annulée ni commencée, et si son studio est publié.
- **Pas de surréservation** : la ligne de la séance est verrouillée (`SELECT … FOR UPDATE`) pendant le comptage et l'insertion. Le même verrou protège la modification de capacité et l'annulation d'une séance. Un test à deux connexions réelles le vérifie (`BookingConcurrencyTest`).
- Une seule réservation par membre et par séance (contrainte unique) ; réserver après une annulation réactive la réservation existante.
- Annulation possible jusqu'à `cancellationDeadlineHours` heures avant le début (paramétrable par studio).
- La capacité d'une séance ne peut pas descendre sous le nombre d'inscrits confirmés.
- **Annulation par le studio** (`BookingManager::cancelSession()`) : toutes les réservations confirmées passent au statut `cancelled_by_studio` (place libérée, pointage impossible), puis l'événement `App\Booking\Event\SessionCancelledEvent` est diffusé avec ces réservations. **C'est le point d'accroche du recrédit des clients** : le MVP n'ayant pas encore de crédits ni de paiement, aucun écouteur n'est branché pour l'instant. Une séance rétablie est de nouveau réservable, mais les réservations annulées le restent (les membres réservent à nouveau).
- Une séance commencée ou annulée n'est plus modifiable ; une séance ne peut pas être déplacée dans le passé.
- L'heure de fin est calculée à partir de la durée du cours : modifier la durée met à jour toutes les séances.
- Heures stockées en heure locale `Europe/Paris` (fixée par `App\Kernel`, indépendamment de `php.ini`).

## Sécurité

- Contrôle d'accès par rôles (`access_control`) et voters (`StudioVoter`, `BookingVoter`).
- CSRF sur tous les formulaires et actions POST (jetons de session), ainsi que sur la déconnexion.
- Mots de passe hachés (`auto`), 12 caractères minimum, refus des mots de passe compromis (Have I Been Pwned).
- Redirection après connexion limitée au même domaine (assurée par Symfony via `HttpUtils` ; `SafeLoginRedirectListener` est une seconde barrière).
- En-têtes HTTP de sécurité (`SecurityHeadersListener`) : `X-Frame-Options`, `frame-ancestors`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`. Pas de CSP complète : l'importmap d'AssetMapper utilise des scripts inline (nonces à prévoir).
- Cookies de session et « rester connecté » : `HttpOnly`, `SameSite=Lax`, `Secure` automatique en HTTPS.
- `roave/security-advisories` empêche l'installation de dépendances vulnérables connues ; `composer audit` en complément.
- Requêtes Doctrine paramétrées ; échappement Twig par défaut.

## Mise en production

L'image Docker fournie est une image de **développement**. En production :

- `APP_ENV=prod` et `APP_DEBUG=0` (sinon profiler et traces d'erreur exposés) ;
- un `APP_SECRET` aléatoire et privé (`php bin/console secrets:set APP_SECRET` ou variable d'environnement) : il signe notamment les cookies « rester connecté ». Ne jamais réutiliser celui de `.env.dev` ;
- HTTPS obligatoire (les cookies passent alors en `Secure`) ;
- un stockage de cache partagé (Redis…) si plusieurs serveurs, pour les limiteurs de tentatives ;
- l'extension PHP `intl` recommandée.

## Qualité

Le service MySQL doit tourner (base `monstudio_test` créée par `docker/mysql/init`).

```bash
docker compose exec app composer qa
```

Commandes individuelles : `composer cs`, `composer cs:fix`, `composer phpstan`, `composer test`.

Les tests reconstruisent le schéma de test à partir des migrations, puis chaque test s'exécute dans une transaction annulée (DAMADoctrineTestBundle). L'heure est simulée (`ClockSensitiveTrait`).

## Structure

```
src/
  Booking/          Règles de réservation (BookingManager, BookingException)
  Controller/       Public, Account, Security, Registration ; Manage/ (gérants) ; Admin/
  Entity/           User, Studio, Discipline, Activity, ClassSession, Booking
  Enum/             Level, BookingStatus
  EventListener/    Génération des slugs
  Form/             Formulaires Symfony
  Schedule/         Planification récurrente, planning hebdomadaire
  Security/         Voters, garde de redirection
  Twig/             Formatage des dates en français (sans extension intl)
assets/
  lib/              Bootstrap et Font Awesome (fichiers vendorisés, licences MIT / OFL)
  styles/app.css    Thème monStudio (surcouche Bootstrap)
tests/
  Unit/ Integration/ Functional/
```

## Limites connues et pistes

- **Paiement** : hors périmètre du MVP (abonnements, carnets, Stripe…).
- **E-mails** : pas encore de confirmation de réservation ni de vérification d'adresse (`symfony/mailer` + `symfonycasts/verify-email-bundle`).
- **Liste d'attente** sur les séances complètes.
- **Symfony 8.1** n'est pas une LTS (fin de maintenance en janvier 2027) : montée en 8.2 prévue dès la sortie de la version stable.
- **Recrédit des clients** : à brancher sur `SessionCancelledEvent` quand les crédits / carnets existeront.
- **Inscription comme gérant** libre (pas de validation par un administrateur) : choix produit à confirmer.
- L'extension PHP `intl` n'est pas requise ; l'ajouter en production reste recommandé.
