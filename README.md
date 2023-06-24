# Laravel Scout Elasticsearch Driver

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

This package makes the [Elasticsearch](https://www.elastic.co/products/elasticsearch) driver for Laravel Scout that works with AWS Elasticsearch and IAM.

## Contents

- [Installation](#installation)
- [Usage](#usage)
- [Credits](#credits)
- [License](#license)

## Installation

You can install the package via composer:

``` bash
composer require lotuashvili/laravel-scout-elastic-aws
```

You must add the Scout service provider and the package service provider in your app.php config:

```php
// config/app.php
'providers' => [
    ...
    Laravel\Scout\ScoutServiceProvider::class,
    ...
    AshrafAkon\Elasticsearch\ElasticsearchProvider::class,
],
```

## Configuration

This package assumes you are following good AWS security and /not/ putting password in your Laravel .env files an instead follows the practices outlined in [Credentials for the AWS SDK for PHP Version 3](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials.html). To enable this, set the following variable in your .env.

```php
ELASTICSEARCH_PROVIDER=aws
```

For local development, inside vagrant for instance, you can use the normal Elasticsearch client by either omitting this variable or setting it as follows.

```php
ELASTICSEARCH_PROVIDER=elastic
```

One thing the AWS client needs is the Region. If you don't already have it in your .env, add it as such.

```php
AWS_REGION=us-west-2
```

For using IAM credentials, add those to .env.

```php
AWS_ACCESS_KEY=
AWS_ACCESS_SECRET=
AWS_ACCESS_TOKEN=
```

## Testing with Scout

If your CI environment does not have access to a working Elasticsearch instance, any indexed Models will cause it to error. To solve this, add the following to your phpunit.xml. The single quotes wrapping the double quotes are the tricky part there.

```xml
<env name="SCOUT_DRIVER" value='"null"' />
```

## Laravel Configuration

After you've published the Laravel Scout package configuration

```bash
php artisan vendor:publish  --provider="AshrafAkon\Elasticsearch\ElasticsearchProvider"
```

you'll need to update the main scout configuration

```php
// config/scout.php
// Set your driver to elasticsearch
    'driver' => env('SCOUT_DRIVER', 'elasticsearch'),

...
    'elasticsearch' => [
        'index' => env('ELASTICSEARCH_INDEX', 'laravel'),
        'hosts' => [
            env('ELASTICSEARCH_HOST', 'http://localhost'),
        ],
    ],
...
```

and this package's configuration

```php
// config/laravel-scout-elastic.php
// set this if you don't want to include it in your .env
    'provider' => env('ELASTICSEARCH_PROVIDER', 'elasticsearch'),
...
    'region' => env('AWS_REGION', 'us-west-2'),
...
```

and enable the artisan job:
```php
// App/Console/Kernel.php
protected $commands = [
    ...
    \AshrafAkon\Elasticsearch\Commands\CreateIndex::class,
    ...
],
```

### Elasticsearch Configuration
Scout will happily throw an error if it cannot create contact your Elasticsearch server or the index doesn't exist.

Creating an index can be kinda arcane, so if the index doesn't exist, you can include the following artisan command in your deployment stack to check if the index exists, and if it doesnt then it will create it.

```bash
php artisan scout:create-index
```

## Usage

Now you can use Laravel Scout as described in the [official documentation](https://laravel.com/docs/10.x/scout)
## Credits

- [Levan Lotuashvili](https://github.com/lotuashvili)
- [Erick Tamayo](https://github.com/ericktamayo)
- [Ashraful Firoz](https://github.com/AshrafAkon)
- [All Contributors](../../contributors)

## License

The MIT License (MIT).
