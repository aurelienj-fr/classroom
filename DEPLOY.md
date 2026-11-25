# Déploiement sur un hébergement mutualisé (OVHcloud, IONOS…)

Ce guide déploie monStudio sur un hébergement PHP classique, **sans Docker, sans worker permanent et éventuellement sans SSH**. Le principe : on prépare une version complète sur son poste (`bin/build-release`), puis on l'envoie par FTP/SFTP.

> Les procédures des hébergeurs évoluent : les chemins de menus et noms d'options cités ci-dessous sont à vérifier dans leur documentation (liens en fin de page).

## alwaysdata (offre gratuite) — configuration de myclassroom

Particularités constatées, à vérifier dans l'interface d'administration :

- **SSH et SFTP** disponibles sur toutes les offres, tâches planifiées avec syntaxe cron ;
- **offre gratuite** : uniquement l'adresse `myclassroom.alwaysdata.net` (pas de domaine personnel), usage personnel et non commercial, 1 Go d'espace ;
- **e-mails** : les adresses se créent dans « Emails > Adresses » sur un domaine ; une adresse en `@myclassroom.alwaysdata.net` n'est pas confirmée par la documentation. L'**envoi** SMTP depuis les serveurs alwaysdata ne demande pas d'authentification (`smtp-myclassroom.alwaysdata.net`, port 587 ou 465).

Étapes :

1. **Base** : « Bases de données > MySQL » > créer une base et un utilisateur. Dans phpMyAdmin, importer le fichier SQL d'initialisation (schéma, disciplines, administrateur).
2. **Configuration** : partir de `deploy/env.prod.local.dist` (ou du fichier préparé pour le compte), renseigner l'utilisateur, le mot de passe et le nom de la base dans `DATABASE_URL`, enregistrer sous `.env.prod.local`, puis `bin/build-release`.
3. **Fichiers** : envoyer le contenu de `build/release/` par SFTP dans `$HOME/www/monstudio/`.
4. **Site** : « Web > Sites » > site `myclassroom.alwaysdata.net` : type **PHP**, version **8.4**, **répertoire racine** `/www/monstudio/public/`. Régler aussi PHP 8.4 pour la ligne de commande (« Environnement »).
5. **Tâche planifiée** : « Avancé > Tâches planifiées » > type commande, `php $HOME/www/monstudio/bin/cron.php`, fréquence horaire (ou toutes les 15 minutes).
6. **Premier accès** : se connecter avec le compte administrateur, puis « Mon profil » pour remplacer le mot de passe temporaire (et l'adresse e-mail si besoin).

Si l'envoi d'e-mails est refusé (expéditeur `noreply@myclassroom.alwaysdata.net` non accepté), créer une adresse sur un domaine dont vous disposez, ou utiliser un service SMTP externe, puis adapter `MAILER_DSN` et `MAILER_FROM`.

## 1. Ce qu'il faut chez l'hébergeur

| Élément | Exigence |
|---|---|
| PHP | **8.4** (8.3 ne suffit pas : Symfony 8 l'exige) |
| Extensions PHP | `pdo_mysql`, `ctype`, `iconv`, `mbstring` ; `intl` recommandée |
| Base de données | MySQL 8.0+ (ou MariaDB 10.6+) |
| HTTPS | certificat SSL activé sur le domaine (Let's Encrypt, généralement inclus) |
| Tâches planifiées (cron) | une exécution par heure suffit |
| Adresse e-mail | une boîte de l'offre, pour l'envoi SMTP des notifications |
| Accès | FTP/SFTP obligatoire ; SSH facultatif mais pratique |

Sur le poste qui prépare la version : **PHP 8.4 et Composer**, plus Git et `zip`.

## 2. Comment monStudio s'adapte au mutualisé

- **E-mails sans worker** : avec `MESSENGER_CONSUME_ON_TERMINATE=1`, les e-mails mis en file pendant une requête sont envoyés juste après la réponse (`App\Messenger\ConsumeOnTerminateListener`) : le visiteur n'attend pas le serveur SMTP. Si l'envoi échoue (serveur SMTP indisponible…), le message reste en file et la **tâche planifiée horaire** (`bin/cron.php`) le renvoie.
- **Pas de SSH nécessaire** : configuration compilée dans `.env.local.php`, assets compilés dans `public/assets/`, SQL des migrations et du premier administrateur générés sur le poste puis importés avec phpMyAdmin.
- **Apache** : `public/.htaccess` route les requêtes vers Symfony ; `deploy/htaccess-root` sert si le site ne peut pas pointer sur `public/`.

## 3. Préparer la base de données

1. Dans l'espace client, créez une base MySQL et notez l'hôte, le nom, l'utilisateur, le mot de passe et la **version** du serveur (affichée dans phpMyAdmin).
2. Générez le SQL de toutes les migrations sur votre poste, à l'aide d'une base MySQL **temporaire** (elle est supprimée ensuite), par exemple celle du Docker Compose de développement :

   ```bash
   docker compose up -d database
   EXPORT_DATABASE_URL="mysql://root:ChangeMeRoot@127.0.0.1:3306/monstudio_export?serverVersion=8.4.0&charset=utf8mb4" bin/export-migrations-sql
   ```

   Résultat : `build/migrations.sql`, qui crée le schéma **et** enregistre les migrations comme exécutées.
3. Importez `build/migrations.sql` dans la base de production avec phpMyAdmin (onglet « Importer »).
4. Créez le premier administrateur. Le mot de passe est demandé, haché localement, jamais affiché :

   ```bash
   php bin/console app:admin:create admin@votre-domaine.fr Prénom Nom --sql
   ```

   Exécutez la ligne `INSERT INTO user …` affichée dans l'onglet « SQL » de phpMyAdmin.

Avec SSH, les étapes 2 à 4 se remplacent par `php bin/console doctrine:migrations:migrate` puis `php bin/console app:admin:create …` (sans `--sql`) sur le serveur.

## 4. Préparer la version

1. Copiez `deploy/env.prod.local.dist` en `.env.prod.local` (fichier ignoré par Git) et remplissez-le : `APP_SECRET`, `DEFAULT_URI`, `DATABASE_URL` (avec la vraie `serverVersion`), `MAILER_DSN`, `MAILER_FROM`.
2. Construisez la version :

   ```bash
   bin/build-release --ovh
   ```

   - `--ovh` ajoute le fichier `.ovhconfig` (PHP 8.4) ;
   - `--root-htaccess` ajoute le `.htaccess` racine, si le site ne peut pas pointer sur `public/` (voir étape 5).

   Résultat : le dossier `build/release/` (et une archive `build/monstudio-<commit>.zip`). Il contient `vendor/`, `public/assets/` et `.env.local.php` (**secrets** : ne jamais le placer dans `public/` ni le partager).

## 5. Envoyer les fichiers et configurer le site

1. Envoyez **le contenu** de `build/release/` dans un dossier dédié de l'hébergement (par exemple `monstudio/`), par SFTP/FTP (FileZilla, ou `lftp`).
2. **Racine du site** : faites pointer le domaine sur le sous-dossier `public/` :
   - OVHcloud : « Hébergements » > « Multisite » > modifier le domaine > dossier racine `monstudio/public` ;
   - IONOS : « Domaines & SSL » > domaine > « Utilisation » > répertoire de destination `monstudio/public`.

   Si ce n'est pas possible, reconstruisez avec `--root-htaccess` et faites pointer le domaine sur `monstudio/` : le `.htaccess` racine sert alors `public/` et rend le reste inaccessible.
3. **PHP 8.4** : OVHcloud lit le `.ovhconfig` (ou réglez la version dans l'espace client) ; IONOS : réglez PHP 8.4 dans l'espace client.
4. Le dossier `var/` doit être accessible en écriture (cache, logs, sessions). C'est le cas par défaut chez la plupart des hébergeurs.
5. Activez le **certificat SSL** du domaine.

## 6. Tâche planifiée (renvoi des e-mails en échec)

- **OVHcloud** : « Hébergements » > « Tâches planifiées - Cron » > ajouter : commande `monstudio/bin/cron.php` (chemin depuis la racine FTP), langage PHP 8.4, fréquence **toutes les heures** (le minimum chez OVHcloud).
- **IONOS (SSH)** : `crontab -e` puis, avec la commande PHP 8.4 de votre contrat (`php8.4` ou `php8.4-cli` selon la date de souscription) :

  ```
  7 * * * * php8.4 $HOME/monstudio/bin/cron.php >/dev/null 2>&1
  ```

`bin/cron.php` équivaut à `php bin/console app:messages:send --time-limit=50`.

## 7. Vérifications après la mise en ligne

- [ ] `https://votre-domaine.fr/` redirige vers `/fr` (ou la langue du navigateur).
- [ ] `https://votre-domaine.fr/.env.local.php` et `/composer.json` renvoient une erreur 404.
- [ ] Connexion avec le compte administrateur, création d'une discipline.
- [ ] Inscription d'un compte de test, réservation : l'e-mail de confirmation arrive, liens en `https://`.
  - Liens en `http://` : ajoutez `TRUSTED_PROXIES=REMOTE_ADDR` dans `.env.prod.local`, reconstruisez et renvoyez `.env.local.php`.
- [ ] La table `messenger_messages` est vide (phpMyAdmin) après quelques minutes.
- [ ] La tâche planifiée apparaît dans les logs de l'hébergeur après une heure.

## 8. Mettre à jour

1. `git pull`, puis `bin/build-release --ovh` (même `.env.prod.local`).
2. Nouvelles migrations ? Générez **uniquement** les nouvelles à partir de la dernière version appliquée en production (dernière ligne de la table `doctrine_migration_versions`) et importez le fichier dans phpMyAdmin **avant** d'envoyer le code :

   ```bash
   EXPORT_DATABASE_URL="mysql://root:ChangeMeRoot@127.0.0.1:3306/monstudio_export?serverVersion=8.4.0&charset=utf8mb4" \
     bin/export-migrations-sql --from='DoctrineMigrations\Version20261123143928'
   ```

3. Envoyez les fichiers (écrasement), puis **supprimez le dossier `var/cache/prod/`** par FTP (avec SSH : `php bin/console cache:clear`).

## 9. Limites du mutualisé et évolution

- Envoi des e-mails après la réponse : avec PHP-FPM, le visiteur ne patiente pas ; avec un Apache en `mod_php`, la connexion peut rester ouverte le temps de l'envoi (quelques secondes au plus, sans bloquer la page affichée).
- Les renvois après échec attendent la tâche planifiée (jusqu'à une heure).
- Un seul serveur : cache, sessions et limiteurs de tentatives sur disque, ce qui convient à ce volume.
- Pour passer à un VPS plus tard : `MESSENGER_CONSUME_ON_TERMINATE=0` et le service `worker` (`messenger:consume async`) du `compose.yaml`.

## Sources

- [alwaysdata — Utiliser une adresse e-mail (SMTP, IMAP)](https://help.alwaysdata.com/en/e-mails/use-an-e-mail-address)
- [alwaysdata — Tâches planifiées](https://help.alwaysdata.com/en/docs/web-hosting/tasks/)
- [alwaysdata — Offres](https://www.alwaysdata.com/fr/offers/)

- [OVHcloud — Environnement, version PHP, « .ovhconfig »](https://docs.ovhcloud.com/fr/guides/web-cloud/web-hosting/configure-your-web-hosting)
- [OVHcloud — Versions disponibles des langages](https://docs.ovhcloud.com/fr/guides/web-cloud/web-hosting/web-hosting-main-info)
- [OVHcloud — Tâches automatisées (CRON)](https://docs.ovhcloud.com/fr/guides/web-cloud/web-hosting/cron-tasks)
- [IONOS — Composer sur l'hébergement web](https://www.ionos.com/digitalguide/websites/web-development/using-php-composer-in-ionos-webhosting-packages/)
- [IONOS — Exécuter des scripts PHP en ligne de commande](https://ionos.com/help/hosting/using-php-for-web-projects/executing-php-scripts-in-the-command-line)
- [IONOS — Gestionnaire de tâches cron](https://www.ionos.com/help/hosting/cron-jobs/cron-job-manager/)
