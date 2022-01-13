# Fork changes

2022-01-13
- Created a new branch: 0.4
- Updated PHP requirement from >=5.6 to ^7.4.
- Removed `php-cs-fixer` as it wasn't compatible with PHP 7.4, nor was it familiar.
- Updated Composer dependencies.
- Modified `Worker` class to work on an injected instance of `ManagerRegistry` instead of `EntityManager`. The worker
  now handles all entity managers registered in the manager registry.
  - This change was made because of hitting a pitfall where a certain queue job used a different manager, and the manager
    wasn't being cleared before each execution. This caused the job to sometimes use stale data as it didn't query already
    loaded entities from the database. It also caused "MySQL server has gone away" issues as the connection on that
    manager wasn't checked by this module.

## SafeQueue

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)

A Laravel Queue worker that's safe for use with Laravel Doctrine

#### When to use SafeQueue

- [x] You use Laravel 5
- [x] You use Laravel Doctrine
- [x] Devops say the CPU usage of `queue:listen` is unacceptable
- [x] You want to do `php artisan queue:work --daemon` without hitting cascading `EntityManager is closed` exceptions

#### Compatibility

Version | Supported Laravel Versions 
------- | -------------------------- 
0.1.* | 5.1, 5.2 
0.2.* | ^5.3.16 
>=0.3.* | ^5.4.9

#### How it Works

SafeQueue overrides a small piece of Laravel functionality to make the queue worker daemon safe for use with Doctrine.
It makes sure that the worker exits if any registered EntityManagers are closed after an exception.
For good measure it also clears all registered managers before working each job.

#### Installation

Install using composer

```
composer require maxbrokman/safe-queue
```

Once you've got the codez add the following to your service providers in `app.php`

```
MaxBrokman\SafeQueue\DoctrineQueueProvider::class
```
##### Lumen

Create the config file `config/safequeue.php` and load it: `$app->configure('safequeue');`
```
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Worker Command Name
    |--------------------------------------------------------------------------
    |
    | Configure the signature / name of the Work Command here. The default
    | is to rename the command to 'doctrine:queue:work', however you can
    | rename it to whatever you want by changing this value.
    |
    | To override the Laravel 'queue:work' command name just set this
    | to a false value or 'queue:work'.
    |
    */
    'command_name' => 'doctrine:queue:work',

];
```

#### Usage

```
php artisan doctrine:queue:work  connection --daemon -sleep=3 --tries=3 ...
```

All options are identical to Laravel's own `queue:work` method.

#### Contributing

PRs welcome. Please send PRs to the relevant branch (`0.1, 0.2, 0.3`) depending on which version of Laravel you are targeting.

Run tests and style fixer.

```
vendor/bin/php-cs-fixer fix
vendor/bin/phpunit
```
