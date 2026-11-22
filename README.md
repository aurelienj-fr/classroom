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

Application : <http://localhost:8080>

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
- Inscription (membre ou gérant), connexion avec « rester connecté », limitation des tentatives (5 / 15 min).

**Gérants (`ROLE_OWNER`)**
- Création et édition de studios (brouillon / publié, délai d'annulation).
- Cours (modèles : discipline, niveau, durée, capacité par défaut).
- Séances : création unitaire ou répétée chaque semaine (jusqu'à 26 semaines), modification, annulation / rétablissement.
- Liste des inscrits et pointage des présences.

**Administrateurs (`ROLE_ADMIN`)**
- Gestion des disciplines (nom, icône Font Awesome).
- Accès à la gestion de tous les studios.

## Règles métier

Centralisées dans `App\Booking\BookingManager` :

- Une séance est réservable si elle n'est ni annulée ni commencée, et si son studio est publié.
- **Pas de surréservation** : la ligne de la séance est verrouillée (`SELECT … FOR UPDATE`) pendant le comptage et l'insertion.
- Une seule réservation par membre et par séance (contrainte unique) ; réserver après une annulation réactive la réservation existante.
- Annulation possible jusqu'à `cancellationDeadlineHours` heures avant le début (paramétrable par studio).
- La capacité d'une séance ne peut pas descendre sous le nombre d'inscrits confirmés.

## Sécurité

- Contrôle d'accès par rôles (`access_control`) et voters (`StudioVoter`, `BookingVoter`).
- CSRF sur tous les formulaires et actions POST (jetons de session), ainsi que sur la déconnexion.
- Mots de passe hachés (`auto`), 12 caractères minimum, refus des mots de passe compromis (Have I Been Pwned).
- Redirection après connexion limitée au même hôte (`SafeLoginRedirectListener`).
- Requêtes Doctrine paramétrées ; échappement Twig par défaut.

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
- **Symfony 8.1** n'est pas une LTS (fin de maintenance en janvier 2027) : prévoir la montée en 8.2.
- L'extension PHP `intl` n'est pas requise ; l'ajouter en production reste recommandé.
