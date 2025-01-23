# Edlib3

Edlib3 consitsts (at the moment of this writing of 2 major components:

-   Hub
-   Content Author

In this article we are going to install Edlib3 using LXD containers
based on this article:
[<https://docs.edlib.com/docs/hosting/>](https://docs.edlib.com/docs/hosting/)
and the
[docker-compose.yml](https://github.com/cerpus/edlib-docker-example/blob/master/docker-compose.yml)
file To find out which specific versions of the components to use

This information means that we need at least two containers: one for the
hub, one for the Content Author. Just because of the different versions
of PHP that is required. But, we're going to use a seperate contaienr
for the meilisearch as well, because of the way that component needs to
be installed (with a script).

## Installation

### Hub

The follwoing components are needed:

-   PHP 8.3
-   Meilisearch v11
-   PostgreSQL v16
-   SMTP server for outgoing mail
-   Redis v7
-   S3 file hosting (optional)

#### LXD container

We're going to use a basic Ubuntu 22.04 container as our starting point.
Within that container we're going to install all required components
except Meilisearch.

#### PHP 8.3

We're going to install PHP 8.3, and use apache2 with mpm_event and fpm
to host it. Included within the setup of PHP 8.3 is installing the
actual PHP code, and setting up the necessary libraries (using
composer).

1\. Add ppa for PHP8.3

`add-apt-repository ppa:ondrej/php`

2\. Install php8.3, including php8.3-fpm, composer and apache2

`apt install php8.3 php8.3-fpm composer apache2`

3\. Configure apache2 to use php8.3-fpm and mdm_event engine

`a2dismod mpm_worker`  
`a2dismod php8.1`

If the native php version of Ubuntu 22.04 was installed, get rid of the
configuration

`a2disconf php8.1-fpm`

Enable php8.3

`a2enmod proxy_fcgi setenvif`  
`a2enconf php8.3-fpm`

4\. Get Edlib3 from git, and make the hub (and only the hub) available
in /var/www/html

`cd $HOME`  
`git clone `[`https://github.com/cerpus/Edlib.git`](https://github.com/cerpus/Edlib.git)  
`cd Edlib`  
`git checkout edlib3`  
`cd /var/www`  
`mv html html.old`  
`ln -sf $HOME/Edlib/sourcecode/hub html`  
`cd html`  
`chown -R www-data:www-data .`  
`composer install`

This gives all kinds of errors, because required extensions are not
available. Install all required extensions:

`apt install php8.3-bcmath php8.3-xml php8.3-zip php8.3-curl php8.3-mbstring`

Later on, it becomes clear we also need two more extensions

`apt install php8.3-redis php8.3-pgsql`

#### Meilisearch

See also
<https://www.meilisearch.com/docs/guides/deployment/running_production>

Step 1. Install Meilisearch using the install script of meilisearch

`apt update`  
`apt install curl`  
`# Install Meilisearch latest version from the script`  
`curl -L `[`https://install.meilisearch.com`](https://install.meilisearch.com)` | sh`  
`mv ./meilisearch /usr/local/bin/`

Step 2. Create a system user

`useradd -d /var/lib/meilisearch -s /bin/false -m -r meilisearch`  
`chown meilisearch:meilisearch /usr/local/bin/meilisearch`

Step 3. Create a configuration file

`mkdir /var/lib/meilisearch/data /var/lib/meilisearch/dumps /var/lib/meilisearch/snapshots`  
`chown -R meilisearch:meilisearch /var/lib/meilisearch`  
`chmod 750 /var/lib/meilisearch`

Fetch an example config

`curl `[`https://raw.githubusercontent.com/meilisearch/meilisearch/latest/config.toml`](https://raw.githubusercontent.com/meilisearch/meilisearch/latest/config.toml)` > /etc/meilisearch.toml`

Finally, update the following lines in the meilisearch.toml file so
Meilisearch uses the directories you created earlier to store its data,
replacing MASTER_KEY with a 16-byte string:

`env = "production"`  
`master_key = "MASTER_KEY"`  
`db_path = "/var/lib/meilisearch/data"`  
`http_addr = "0.0.0.0:7700"`  
`dump_dir = "/var/lib/meilisearch/dumps"`  
`snapshot_dir = "/var/lib/meilisearch/snapshots"`

Step 4. Run as a service Run this command to create a service file in
/etc/systemd/system:

`cat << EOF > /etc/systemd/system/meilisearch.service`  
`[Unit]`  
`Description=Meilisearch`  
`After=systemd-user-sessions.service`  
  
`[Service]`  
`Type=simple`  
`WorkingDirectory=/var/lib/meilisearch`  
`ExecStart=/usr/local/bin/meilisearch --config-file-path /etc/meilisearch.toml`  
`User=meilisearch`  
`Group=meilisearch`  
`Restart=on-failure `  
  
`[Install]`  
`WantedBy=multi-user.target`  
`EOF`

With your service file now ready to go, activate the service using
systemctl:

`systemctl enable meilisearch`  
`systemctl start meilisearch`

#### Postgres v16

To install Postgres v 16, we need to install extra repositories first:

`sh -c 'echo "deb `[`http://apt.postgresql.org/pub/repos/apt`](http://apt.postgresql.org/pub/repos/apt)` $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'`  
`curl -fsSL `[`https://www.postgresql.org/media/keys/ACCC4CF8.asc`](https://www.postgresql.org/media/keys/ACCC4CF8.asc)` | sudo gpg --dearmor -o /etc/apt/trusted.gpg.d/postgresql.gpg`  
`apt update`  
`apt install postgresql-16 postgresql-contrib-16`

#### SMTP server (postfix)

`apt install postfix`

#### Redis v7

We're going to use the snap version of redis

`snap install redis`

#### Node v18

The hub uses vite.js, and that needs to be build so we get a manifest.
According to the package.json file we need node.js v18 for that.

We're going to install Node.js using the Node Version Manager, so we can
update node.js in an easy way if needed.

`curl -o- `[`https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.3/install.sh`](https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.3/install.sh)` | bash`

You can check the available versions of node.js by using the following
command

`nvm ls-remote`

Now install node v18

`nvm install 18`

### Content Author

We are going to try to install teh Content Author on the URL
edlib.domain.com/ca

The following components are needed:

-   PHP 8.3
-   MySQL/MariaDB v11
-   Redis v7
-   S3 file hosting (optional)

#### PHP 8.3

We're going to install PHP 8.3, and use apache2 with mpm_event and fpm
to host it. Included within the setup of PHP 8.3 is installing the
actual PHP code, and setting up the necessary libraries (using
composer).

1\. Add ppa for PHP8.3

`add-apt-repository ppa:ondrej/php`

2\. Install php8.3, including php8.3-fpm, composer and apache2

`apt install php8.3 php8.3-fpm composer apache2`

3\. Configure apache2 to use php8.3-fpm and mdm_event engine

`a2dismod mpm_worker`  
`a2dismod php8.1`

If the native php version of Ubuntu 22.04 was installed, get rid of the
configuration

`a2disconf php8.1-fpm`

Enable php8.3

`a2enmod proxy_fcgi setenvif`  
`a2enconf php8.3-fpm`

As laravel php also uses the cli version of php for the php artisan
commands, make sure that cli is also version 8.3:

`sudo update-alternatives --set php /usr/bin/php8.3 `  
`sudo update-alternatives --set phar /usr/bin/phar8.3 `  
`sudo update-alternatives --set phar.phar /usr/bin/phar.phar8.3 `  
`sudo update-alternatives --set phpize /usr/bin/phpize8.3 `  
`sudo update-alternatives --set php-config /usr/bin/php-config8.3 `

4\. Get Edlib3 from git, and make the ca (and only the ca) available in
/var/www/html

`cd $HOME`  
`git clone `[`https://github.com/cerpus/Edlib.git`](https://github.com/cerpus/Edlib.git)  
`cd Edlib`  
`git checkout edlib3`  
`cd /var/www`  
`mv html html.old`  
`mkdir html`  
`ln -sf $HOME/Edlib/sourcecode/apis/contentauthor html/ca`  
`cd html/ca`  
`chown -R www-data:www-data .`  
`composer install`

This gives all kinds of errors, because required extensions are not
available. Install all required extensions:

`apt install  apt install php2-zip php8.2-xml php8.2-curl`

We're still getting an error on composer install:

`Uncaught Error: Class "Normalizer" not found in /usr/share/php/Symfony/Component/String/AbstractUnicodeString.php:31`

We're missing php8.2-intl, so install it.

`apt install php8.2-intl`

This results in

`Uncaught Error: Call to undefined function Symfony\Component\String\mb_ord() in /usr/share/php/Symfony/Component/String/AbstractUnicodeString.php:555`

Install php8.2-mbstring

`apt install php8.2-mbstring`

We still get the follwoing errors. Don't know (yet) whther this is an
actual issue:

`Class Cerpus\QuestionBankClient\DataObjects\QuestionDataObject located in ./vendor/cerpus/questionbank-client/src/DataObjects/v2/QuestionDataObject.php does not comply with psr-4 autoloading standard. Skipping.`  
`Class Cerpus\QuestionBankClient\DataObjects\MetadataDataObject located in ./vendor/cerpus/questionbank-client/src/DataObjects/v2/MetadataDataObject.php does not comply with psr-4 autoloading standard. Skipping.`  
`Class Cerpus\QuestionBankClient\DataObjects\SearchDataObject located in ./vendor/cerpus/questionbank-client/src/DataObjects/v2/SearchDataObject.php does not comply with psr-4 autoloading standard. Skipping.`  
`Class Cerpus\QuestionBankClient\DataObjects\QuestionsetsDataObject located in ./vendor/cerpus/questionbank-client/src/DataObjects/v2/QuestionsetsDataObject.php does not comply with psr-4 autoloading standard. Skipping.`  
`Class Cerpus\QuestionBankClient\DataObjects\AnswerDataObject located in ./vendor/cerpus/questionbank-client/src/DataObjects/v2/AnswerDataObject.php does not comply with psr-4 autoloading standard. Skipping.`

Later on, it becomes clear we also need two more extensions

`apt install php8.2-redis php8.2-mysql`

#### MariaDB v11

1\. Add repo of mariadb v11.0 to the installation

`curl -LsS `[`https://downloads.mariadb.com/MariaDB/mariadb\_repo\_setup`](https://downloads.mariadb.com/MariaDB/mariadb\_repo\_setup)` | bash -s -- --mariadb-server-version=11.0`  
` apt update`

2\. Install Mariadb v11

`apt install mariadb-server mariadb-client -y`

#### Redis v7

We're going to use the snap version of redis

`snap install redis`

#### Node v16

The content author needs node.js packages. According to the package.json
file we need node.js v16. I first tried to set it up like the hub using
nvm, but that does not really work very good, as notjes is installed in
such a way that it is not put in the path variable, and that causes all
kinds of issues when trying to rin npm install during configuration.

So for the Content Author we're going to install notjes v16 in the
traditional way;

Install the Node.js source for version 16:

`curl -sL `[`https://deb.nodesource.com/setup_16.x`](https://deb.nodesource.com/setup_16.x)` | sudo -E bash -`

Install Node.js v 16

`apt install nodejs`

This will also automatically install (a matching version of) npm.

## Configuration

### Hub

For the configuration of the hub we're basing our configuration on the
[docker-compose.yml](https://github.com/cerpus/edlib-docker-example/blob/master/docker-compose.yml)
file and the startup_scripts that are in the docker configuration
itself.

Step 1. Create .env file (in the app folder) with an empty APP_KEY

`cd /var/www/html/ca`  
`cat << EOF > .env`  
`APP_KEY=`  
`EOF`

Step 2. Create new APP_KEY

`php artisan key:generate`

Step 3. Set the following ENVIRONMENT variables based on the
[docker-compose.yml](https://github.com/cerpus/edlib-docker-example/blob/master/docker-compose.yml)

`APP_URL=ihttps://edlib.domain.com`  
`CACHE_DRIVER=redis`  
`CONTENTAUTHOR_HOST=`[`https://caedlib.domian.com`](https://ca.edlib.domian.com)  
`CONTENTAUTHOR_KEY=h5p`  
`CONTENTAUTHOR_SECRET=secret2`  
`DB_HOST=127.0.0.1`  
`DB_DATABASE=postgres`  
`DB_USERNAME=edlib`  
`DB_PASSWORD=secret`  
`MEILISEARCH_HOST=`[`http://edlib-meilisearch.domain.com:7700`](http://edlib-meilisearch.domain.com:7700)  
`MEILISEARCH_KEY=masterkey1234567`  
`REDIS_DB='0'`  
`REDIS_CACHE_DB='2'`  
`REDIS_HOST=127.0.0.1`  
`QUEUE_CONNECTION=redis`  
`SESSION_DRIVER=redis`

Step 4. Setup PostgresQL We've chosen to use the database postgres,
because that is available by default. No need to create it. It is owned
by user postgres, but we're not using user postgres, but user edlib. So,
set up user edlib with password secret, and grant permission to the
database postgres: a. Login

`sudo -u postgres psql`

b\. Create user

`CREATE USER edlib with SUPERUSER PASSWORD 'secret'`

c\. Grant access to database postgres

`GRANT ALL PRIVILEGES ON DATABASE "postgres" to edlib;`

d\. Quit

`\q`

Step 5. Run startup scripts as done in container edlib3-hub-startup-1
The file to run is prod-setup.sh, and the docker-copose.yml file
mentions a mapping of a local file to prod-startup.sh in the container:

`volumes:`  
`     - hub_storage:/app/storage`  
`     - ./hub/startup.sh:/usr/local/bin/prod-startup.sh:ro`

So we need to run the commands found in hub/startup.sh of the repository
[edlib-docker-example](https://github.com/cerpus/edlib-docker-example):

`#!/bin/sh`  
`set -eu`  
  
`CA="${CONTENTAUTHOR_HOST}"`  
`KEY="${CONTENTAUTHOR_LTI_KEY}"`  
`SECRET="${CONTENTAUTHOR_LTI_SECRET}"`  
  
`startup.sh`  
  
`if [ ! -f "storage/app/first-time-setup-completed" ]; then`  
`  echo -ne "$KEY\n$SECRET\n" | php artisan edlib:add-lti-tool 'Content Author' \`  
`    "`[`https://$CA/lti-content/create`](https://$CA/lti-content/create)`" \`  
`    --send-name \`  
`    --send-email \`  
`    --edlib-editable`  
  
`  echo -ne "$KEY\n$SECRET\n" | php artisan edlib:add-lti-tool 'CA admin (to be moved)' \`  
`    "`[`https://$CA/lti/admin`](https://$CA/lti/admin)`" \`  
`    --send-name \`  
`    --send-email \`  
`    --edlib-editable`

`  touch storage/app/first-time-setup-completed`  
`fi`

Taking this script apart means firts running startup.sh (found in folder
docker/php/satrtup.sh):

`#!/bin/sh`  
`set -ex`

`php artisan migrate --force`  
`php artisan scout:index 'App\Models\Content'`  
`php artisan scout:sync-index-settings`

So, this means we're going to execute these command:

`php artisan migrate --force`  
`php artisan scout:index 'App\Models\Content'`  
`php artisan scout:sync-index-settings`  
  
`echo -ne "h5p\nsecret2\n" | php artisan edlib:add-lti-tool 'Content Author' \`  
`    "`[`https://edlib.domain.com/ca/lti-content/create`](https://edlib.domain.com/ca/lti-content/create)`" \`  
`    --send-name \`  
`    --send-email \`  
`    --edlib-editable`  
  
`echo -ne "h5p\nsecret2\n" | php artisan edlib:add-lti-tool 'CA admin (to be moved)' \`  
`    "`[`https://edlib.domain.com/ca/lti/admin`](https://edlib.domain.com/ca/lti/admin)`" \`  
`    --send-name \`  
`    --send-email \`  
`    --edlib-editable`

Step 6. Enable scheduler Set the following crontab for the apache user

`crontab -u www-data -e`

Set the following entry:

`* * * * * cd /var/www/html; php artisan schedule:run > /dev/null 2>&1`

Step 7. Set up Queue worker using supervisor Install supervisor

`sudo apt-get install supervisor`

Supervisor configuration files are typically stored in the
/etc/supervisor/conf.d directory. Within this directory, you may create
any number of configuration files that instruct supervisor how your
processes should be monitored. For example, let's create a
laravel-worker.conf file that starts and monitors queue:work processes:

`[program:laravel-worker]`  
`process_name=%(program_name)s_%(process_num)02d`  
`command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600`  
`autostart=true`  
`autorestart=true`  
`stopasgroup=true`  
`killasgroup=true`  
`user=www-data`  
`numprocs=2`  
`redirect_stderr=true`  
`stdout_logfile=/var/www/html/storage/logs/worker.log`  
`stopwaitsecs=3600`

Step 8. Configure apache2 Set up the following apache2 virtual host file
and make sure the document root is the public folder of the application.
Also, include the configuration of the .htaccess file in the public
folder in the configuration of the virtual host instead of relying on
the .htaccess file.

`<VirtualHost *:80>`  
`       # The ServerName directive sets the request scheme, hostname and port that`  
`       # the server uses to identify itself. This is used when creating`  
`       # redirection URLs. In the context of virtual hosts, the ServerName`  
`       # specifies what hostname must appear in the request's Host: header to`  
`       # match this virtual host. For the default virtual host (this file) this`  
`       # value is not decisive as it is used as a last resort host regardless.`  
`       # However, you must set it for any further virtual host explicitly.`  
`       #ServerName www.example.com`  
  
`       ServerName edlib.domain.com`  
  
`       ServerAdmin webmaster@domain.com`  
`       DocumentRoot /var/www/html/public`  
  
`       # Available loglevels: trace8, ..., trace1, debug, info, notice, warn,`  
`       # error, crit, alert, emerg.`  
`       # It is also possible to configure the loglevel for particular`  
`       # modules, e.g.`  
`       #LogLevel info ssl:warn`  
`       `<Directory /var/www>  
`          Options -Indexes +FollowSymLinks`  
`       `</Directory>  
  
`       `<Directory /var/www/html/public>  
`          Options -Indexes +FollowSymLinks`  
`          AllowOverride None`  
`          Require all granted`  
`       `</Directory>  
  
`       RewriteEngine On`  
  
`       # Handle Authorization Header`  
`       RewriteCond %{`[`HTTP:Authorization`](HTTP:Authorization)`} .`  
`       RewriteRule .* - [E=HTTP_AUTHORIZATION:%{`[`HTTP:Authorization`](HTTP:Authorization)`}]`  
  
`       # Redirect Trailing Slashes If Not A Folder...`  
`       RewriteCond %{DOCUMENT_ROOT}%{REQUEST_FILENAME} !-d`  
`       RewriteCond %{REQUEST_URI} (.+)/$`  
`       RewriteRule ^ %1 [L,R=301]`  
  
`       # Handle Front Controller...`  
`       RewriteCond %{DOCUMENT_ROOT}%{REQUEST_FILENAME} !-d`  
`       RewriteCond %{DOCUMENT_ROOT}%{REQUEST_FILENAME} !-f`  
  
`       ErrorLog ${APACHE_LOG_DIR}/error.log`  
`       CustomLog ${APACHE_LOG_DIR}/access.log combined`  
  
`       # For most configuration files from conf-available/, which are`  
`       # enabled or disabled at a global level, it is possible to`  
`       # include a line for only one particular virtual host. For example the`  
`       # following line enables the CGI configuration for this host only`  
`       # after it has been globally disabled with "a2disconf".`  
`       #Include conf-available/serve-cgi-bin.conf`  
</VirtualHost>

Also make sure that the .env configuration is available in the folder
public, by placing a symbolic link

`ln -sf ../.env`

Step 9. Disable Telescope The first time I tried to load the hub in the
browser I got errors about telescope. I don't want it in production, so
disable it in the .env file:

`TELESCOPE_ENABLED=false`

Step 10. Install npm packages for Vite

`npm install`  
`npm run build`

Step 11. Reverse proxying We use a reverse proxy to terminate HTTPS
requests. In this case the css fiels are not laoded correctly, because
they are loaded using http. To fix that add the following to .env

`ASSET_URL=htts://edlib.domain.com`

Also we need to force tha app tu use HTTPS, otherwise forms are not
secore and also signed url's will not work. I decided to extend
/app/Providers/AppServiceProvider.php with the following code at the end
of the boot() function:

`       $force_https = getenv('FORCE_HTTPS');`  
`       if ($force_https !== null && ($force_https === 'true' || $force_https === true))`  
`       {`  
`           // generated urls will use https`  
`           URL::forceScheme('https');`  
  
`           // signing urls keep working `  
`           $this->app['request']->server->set('HTTPS', true);`  
`       }`

Now we can force HTTPS by setting the following in the .env file:

`FORCE_HTTPS=true`

### Content Author

As with the hub we're basing our configuration on the
[docker-compose.yml](https://github.com/cerpus/edlib-docker-example/blob/master/docker-compose.yml)
file and the startup_scripts that are in the docker configuration
itself.

Step 1. Create .env file (in the app folder) with an empty APP_KEY

`cd /var/www/html/ca`  
`cat << EOF > .env`  
`APP_KEY=`  
`EOF`

Step 2. Create new APP_KEY

`php artisan key:generate`

Step 3. Set the following ENVIRONMENT variables based on the
[docker-compose.yml](https://github.com/cerpus/edlib-docker-example/blob/master/docker-compose.yml)

`APP_URL=`[`https://ca.edlib.domain.com`](https://ca.edlib.domain.com)  
`CACHE_DRIVER=redis`  
`DB_HOST=127.0.0.1`  
`DB_DATABASE=edlib3_ca`  
`DB_USERNAME=root`  
`DB_PASSWORD=secret`  
`ENABLE_EDLIB2='false'`  
`H5P_CONSUMER_KEY=h5p`  
`H5P_CONSUMER_SECRET=secret2`  
`H5P_IS_HUB_ENABLED='true'`  
`LOG_CHANNEL=stderr`  
`LOG_STDERR_FORMATTER='null'`  
`REDIS_DB='0'`  
`REDIS_CACHE_DB='1'`  
`REDIS_HOST=127.0.0.1`  
`SESSION_DRIVER=redis`

Step 4. Setup mariadb We've chosen to use the database edlib3_ca and a
user edlib. So, set up user edlib with password secret, and grant
permission to the database edlib3_ca: a. Login as root

`mysql -p`

b\. Create user

`CREATE USER edlib with identified by 'secret'`

c\. Create database

`create database edlib3_ca;`

d\. Grant access to database edlib3_ca (actually grant access to all
databases)

`GRANT ALL PRIVILEGES ON *.* to edlib;`

Step 5. Run startup scripts as done in container edlib3-hub-startup-1
The file to run is prod-setup.sh, and the docker-copose.yml file
mentions a mapping of a local file to prod-startup.sh in the container:

`volumes:`  
`     - ca_storage:/app/storage`  
`     - ./contentauthor/startup.sh:/usr/local/bin/prod-startup.sh:ro`

So we need to run the commands found in contentauthor/startup.sh of the
repository
[edlib-docker-example](https://github.com/cerpus/edlib-docker-example):

`#!/bin/sh`  
`set -e`  
  
`startup.sh`  
  
`if [ ! -f "storage/app/first-time-setup-completed" ]; then`  
`  # Pre-install libraries`  
`  php artisan h5p:library-hub-cache`  
`  php artisan h5p:library-install \`  
`     H5P.Accordion \`  
`     H5P.AppearIn \`  
`     H5P.Audio \`  
`     H5P.AudioRecorder \`  
`     H5P.Blanks \`  
`     H5P.Boardgame \`  
`     H5P.CoursePresentation \`  
`     H5P.Dialogcards \`  
`     H5P.DocumentationTool \`  
`     H5P.DragQuestion \`  
`     H5P.DragText \`  
`     H5P.Flashcards \`  
`     H5P.GreetingCard \`  
`     H5P.GuessTheAnswer \`  
`     H5P.IFrameEmbed \`  
`     H5P.ImageHotspotQuestion \`  
`     H5P.ImageHotspots \`  
`     H5P.ImpressPresentation \`  
`     H5P.InteractiveVideo \`  
`     H5P.MarkTheWords \`  
`     H5P.MemoryGame \`  
`     H5P.MultiChoice \`  
`     H5P.MultiMediaChoice \`  
`     H5P.QuestionSet \`  
`     H5P.Questionnaire \`  
`     H5P.SingleChoiceSet \`  
`     H5P.Summary \`  
`     H5P.Timeline \`  
`     H5P.TrueFalse \`  
`     H5P.TwitterUserFeed`  
  
`  touch storage/app/first-time-setup-completed`  
`fi`

Taking this script apart means first running startup.sh (found in folder
docker/php/satrtup.sh):

`#!/bin/sh`  
`set -ex`  
  
`php artisan migrate --force`

So, this means we're going to execute these command:

`php artisan migrate --force`  
  
`php artisan h5p:library-hub-cache`  
`php artisan h5p:library-install \`  
`     H5P.Accordion \`  
`     H5P.AppearIn \`  
`     H5P.Audio \`  
`     H5P.AudioRecorder \`  
`     H5P.Blanks \`  
`     H5P.Boardgame \`  
`     H5P.CoursePresentation \`  
`     H5P.Dialogcards \`  
`     H5P.DocumentationTool \`  
`     H5P.DragQuestion \`  
`     H5P.DragText \`  
`     H5P.Flashcards \`  
`     H5P.GreetingCard \`  
`     H5P.GuessTheAnswer \`  
`     H5P.IFrameEmbed \`  
`     H5P.ImageHotspotQuestion \`  
`     H5P.ImageHotspots \`  
`     H5P.ImpressPresentation \`  
`     H5P.InteractiveVideo \`  
`     H5P.MarkTheWords \`  
`     H5P.MemoryGame \`  
`     H5P.MultiChoice \`  
`     H5P.MultiMediaChoice \`  
`     H5P.QuestionSet \`  
`     H5P.Questionnaire \`  
`     H5P.SingleChoiceSet \`  
`     H5P.Summary \`  
`     H5P.Timeline \`  
`     H5P.TrueFalse \`  
`     H5P.TwitterUserFeed`

The follwoing errors are emitted. To be sorted later

`  - H5P.AppearIn: Not found in cache, skipping`  
`  - H5P.Boardgame: Not found in cache, skipping`  
`  - H5P.CoursePresentation: Failed`  
`     Validating h5p package failed.`  
`  - H5P.DocumentationTool: Failed`  
`     Validating h5p package failed.`  
`  - H5P.GreetingCard: Not found in cache, skipping`  
`  - H5P.ImpressPresentation: Not found in cache, skipping`  
`  - H5P.InteractiveVideo: Failed`  
`     Validating h5p package failed.`  
`  - H5P.MarkTheWords: Failed`  
`     Validating h5p package failed.`  
`  - H5P.MultiChoice: Failed`  
`     Validating h5p package failed.`  
`  - H5P.MultiMediaChoice: Failed`  
`     Validating h5p package failed.`  
`  - H5P.QuestionSet: Failed`  
`     Validating h5p package failed.`  
`  - H5P.TwitterUserFeed: Not found in cache, skipping`

Step 6. Create proper symlinks
The contantauthor also needs proper symlinks into the public fiolder. You can create those using php artisisan storage:link. It will creaet 3 symbolic links.
`php artisan storage:link` 

Step 7. Enable scheduler Set the following crontab for the apache user

`crontab -u www-data -e`

Set the following entry:

`* * * * * cd /var/www/html; php artisan schedule:run > /dev/null 2>&1`

Step 8. Set up Queue worker using supervisor Install supervisor

`sudo apt-get install supervisor`

Supervisor configuration files are typically stored in the
/etc/supervisor/conf.d directory. Within this directory, you may create
any number of configuration files that instruct supervisor how your
processes should be monitored. For example, let's create a
laravel-worker.conf file that starts and monitors queue:work processes:

`[program:laravel-worker]`  
`process_name=%(program_name)s_%(process_num)02d`  
`command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600`  
`autostart=true`  
`autorestart=true`  
`stopasgroup=true`  
`killasgroup=true`  
`user=www-data`  
`numprocs=2`  
`redirect_stderr=true`  
`stdout_logfile=/var/www/html/storage/logs/worker.log`  
`stopwaitsecs=3600`

Step 9. Configure apache2 Set up the following apache2 virtual host file
and make sure the document root is the public folder of the application.
Also, include the configuration of the .htaccess file in the public
folder in the configuration of the virtual host instead of relying on
the .htaccess file.

`<VirtualHost *:80>`  
`       # The ServerName directive sets the request scheme, hostname and port that`  
`       # the server uses to identify itself. This is used when creating`  
`       # redirection URLs. In the context of virtual hosts, the ServerName`  
`       # specifies what hostname must appear in the request's Host: header to`  
`       # match this virtual host. For the default virtual host (this file) this`  
`       # value is not decisive as it is used as a last resort host regardless.`  
`       # However, you must set it for any further virtual host explicitly.`  
`       #ServerName www.example.com`  
  
`       ServerName ca.edlib.domain.com`  
  
`       ServerAdmin webmaster@domain.com`  
`       DocumentRoot /var/www/html/public`  
  
`       # Available loglevels: trace8, ..., trace1, debug, info, notice, warn,`  
`       # error, crit, alert, emerg.`  
`       # It is also possible to configure the loglevel for particular`  
`       # modules, e.g.`  
`       #LogLevel info ssl:warn`  
`       `<Directory /var/www>  
`          Options -Indexes +FollowSymLinks`  
`       `</Directory>  
  
`       `<Directory /var/www/html/public>  
`          Options -Indexes +FollowSymLinks`  
`          AllowOverride None`  
`          Require all granted`  
`       `</Directory>  
  
`       RewriteEngine On`  
  
`       # Handle Authorization Header`  
`       RewriteCond %{`[`HTTP:Authorization`](HTTP:Authorization)`} .`  
`       RewriteRule .* - [E=HTTP_AUTHORIZATION:%{`[`HTTP:Authorization`](HTTP:Authorization)`}]`  
  
`       # Redirect Trailing Slashes If Not A Folder...`  
`       RewriteCond %{DOCUMENT_ROOT}%{REQUEST_FILENAME} !-d`  
`       RewriteCond %{REQUEST_URI} (.+)/$`  
`       RewriteRule ^ %1 [L,R=301]`  
  
`       # Handle Front Controller...`  
`       RewriteCond %{DOCUMENT_ROOT}%{REQUEST_FILENAME} !-d`  
`       RewriteCond %{DOCUMENT_ROOT}%{REQUEST_FILENAME} !-f`  
  
`       ErrorLog ${APACHE_LOG_DIR}/error.log`  
`       CustomLog ${APACHE_LOG_DIR}/access.log combined`  
  
`       # For most configuration files from conf-available/, which are`  
`       # enabled or disabled at a global level, it is possible to`  
`       # include a line for only one particular virtual host. For example the`  
`       # following line enables the CGI configuration for this host only`  
`       # after it has been globally disabled with "a2disconf".`  
`       #Include conf-available/serve-cgi-bin.conf`  
</VirtualHost>

Also make sure that the .env configuration is available in the folder
public, by placing a symbolic link

`ln -sf ../.env`

Step 10. Install and build the required Node.js packages Install using
npm

`cd /var/www/html/`  
`npm install`

Build

`npm run production`
