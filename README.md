# Laravel Filterable

[![License](https://img.shields.io/packagist/l/fomvasss/laravel-filterable.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-filterable)
[![Build Status](https://img.shields.io/github/stars/fomvasss/laravel-filterable.svg?style=for-the-badge)](https://github.com/fomvasss/laravel-filterable)
[![Latest Stable Version](https://img.shields.io/packagist/v/fomvasss/laravel-filterable.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-filterable)
[![Total Downloads](https://img.shields.io/packagist/dt/fomvasss/laravel-filterable.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-filterable)
[![Quality Score](https://img.shields.io/scrutinizer/g/fomvasss/laravel-filterable.svg?style=for-the-badge)](https://scrutinizer-ci.com/g/fomvasss/laravel-filterable)

Package for easy filtering and searching in your Eloquent models

----------

## Installation

Run from the command line:

```bash
composer require fomvasss/laravel-filterable
```

## Publishing

```bash
php artisan vendor:publish --provider="Fomvasss\Filterable\ServiceProvider"
```

## Integration

Usage in Eloquent models trait

```Fomvasss\Filterable\Filterable```

## Usage

`app/Models/Article.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Fomvasss\Filterable\Filterable;

class Article extends Model
{
    use Filterable;
    
    protected $filterable = [
        'title' => 'like',              // http://site.test/post?filter[title]=Some+title
        'price' => 'between',           // http://site.test/post?filter[price_from]=120&filter[price_to]=380
        'category_id' => 'equal',       // http://site.test/post?filter[category_id]=2
        'status' => 'in',               // http://site.test/post?filter[status][]=publish&filter[status][]=active or http://site.test/post?filter[status]=publish|active
        'created_at' => 'between_date', //http://site.test/post?filter[created_at_from]=14.10.2018&filter[created_at_to]=24.11.2018
        'updated_at' => 'equal_date',   //http://site.test/post?filter[updated_at]=14.10.2018
        'price_exists' => 'custom',     //http://site.test/post?filter[price_exists]=1 - check exists price

        // Relation model field (for `equal` and `in`)
        'user.name' => 'like',          // http://site.test/post?filter[user.name]=Ева%20Максимовна%20Терентьева
    ];
    
    protected $searchable = [
        'name', 'email', 'contacts.city'
    ];
}
```

`app/Http/Controllers/Article.php`

```php
<?php 

namespace App\Http\Controllers;

use Fomvasss\MediaLibraryExtension\MediaLibraryManager;

class HomeController extends Controller 
{
    public function index(Request $request)
    {
        $articles = \App\Model\Article::filterable($request->filter)
            ->searchable($request->q)
            ->get();

        return $articles;
    }
}
```

## Links