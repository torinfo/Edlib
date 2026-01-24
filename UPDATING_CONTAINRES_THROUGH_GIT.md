# Updating Edlib3 containers through Git

## Overview
Updating Hub
Updating CA

## Updating Hub

1. Make sure you have a backup of the code and the database
2. Navigate to the Hub's Git repository directory:
   ```bash
   cd /path/to/hub/repository
   ```
3. Pull the latest changes from the Git repository:
   ```bash
   git pull
   ```
4. Go to the sourrcecode/hub directory:
   ```bash
   cd sourcecode/hub
   ```
5. Update PHP dependencies
   ```bash
   composer update
   ```
6. Migrate the database to apply any new changes:
   ```bash
   ./artisan migrate
   ```
7. Clear the application cache:
   ```bash
    ./artisan cache:clear
    ```
8. Clear the views cache:
   ```bash
    ./artisan view:clear
    ```
9. Upadte/Sync index settings
   ```bash
   ./artisan scout:sync-index-settings
   ```
10. Check and install correct version of NodeJS
11. Rebuild Vice if needed
   ```bash
   npm install
   npm run build
   ```
11. Restart the Hub container (not strictly necessary)

## Updating CA
1. Make sure you have a backup of the code and the database
2. Navigate to the CA's Git repository directory:
   ```bash
   cd /path/to/ca/repository
   ```
3. Pull the latest changes from the Git repository:
   ```bash
    git pull
    ```
4. Go to the sourcecode/apis/contentauthor directory:
    ```bash
    cd sourcecode/apis/contentauthor
    ```
5. Update PHP dependencies
   ```bash
   composer update
   ```
6. Migrate the database to apply any new changes:
   ```bash
   ./artisan migrate
   ```
7. Clear the application cache:
   ```bash
    ./artisan cache:clear
    ```
8. Clear the views cache:
   ```bash
    ./artisan view:clear
   ```
9. Check and install correct version of NodeJS  
10. Rebuild mix files:
   ```bash
   npm install
   npm run prod
   ```