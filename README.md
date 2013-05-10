## A Simple Doctrine 2 Wrapper

Dub is is a set of classes designed to ease Doctrine 2 development.  It uses simple conventions and a bit of magic to limit the amount of bootstrap code you need to write.

### Creating a Model

```php
<?php namespace Dotink\Test
{
	use Dotink\Dub\Model;

	class User extends Model
	{
		protected $id = NULL;
		protected $name = NULL;
		protected $emailAddress = NULL;
	}

}
```

A Dub model should extend `Dotink\Dub\Model`.  All `protected` properties on a model will be considered fields and be given setters and getters.

### Configuring a Model

```php
Model::configure('Dotink\Test\User', [
	'pkey' => 'id',
	'fields' => [
		'id' => ['type' => 'serial']
	]
]);
```

Fields which are left unconfigured recieve the default type `string` with no additional constraints.  By default, a class will map to a repository matching an undercored short name.  So, `Dotink\Test\BlogArticle` would map to `blog_articles` repo.  If you need to map to a different repository you can set it in the configuration:

```php
Model::configure('Dotink\Test\BlogArticle', [
	'repo' => 'Articles'
	'pkey' => 'id',
	'fields' => [
		'id' => ['type' => 'serial']
	]
]);
```

### Using a Model

```php
$user = new User();
$user->setName('Matthew J. Sahagian');
$user->setEmailAddress('info@dotink.org');

printf(
	'%s has an e-mail address of %s',
	$user->getName(),
	$user->getEmailAddress()
);
```

#### Populating

If you have an array of data from a form or something similar you can populate the model all at once:

```php
$user->populate($data);
```

The `populate()` method will transform lower camel case and underscore keys to call the appropriate `set*()` method:

```php
$user->populate([
	'name'          => 'John Doe',
	'email_address' => 'john.d@example.com'
]);
```

### Creating a Database

```php
$databases  = new Dotink\Dub\DatabaseManager();
$connection = [
	'driver' => 'pdo_sqlite',
	'path'   => 'test.db'
];

$databases->add('default', $connection);
```

### Writing the Schema

```php
$databases->createSchema('default', ['Dotink\Test\User']);
```

You can also map models 1-by-1:

```php
$databases->map('default', 'Dotink\Test\User');
$databases->map('default', 'Dotink\Test\Article');
$databases->map('default', 'Dotink\Test\Contact');

$database->createSchema('default');
```

### Persisting a Model

```php
$databases['default']->persist($user);
$databases['default']->flush();
```

Alternatively you can call `store()` and pass in the entity manager to persist or update depending on the current state:

```php
$user->store($databases['default']);
```

### Removing a Model

```php
$database['default']->remove($user);
```

Or, similar to storing:

```php
$user->remove($database['default']);
```

### Validation

Validation is being added to the base Model class based on configuration.  Models will have the validation logic called whenever they are persisted or updated, so you don't need to call anything extra.  Currently Dub supports validating:

- Non-nullable fields are not NULL
- String fields with a specified length do not exceed that length

#### Failed Validation

If validation fails a `Dotink\Dub\ValidationException` will be thrown.  You can wrap `store()` calls with try/catch blocks and get a list of validation errors organized by field using the `fetchValidationMessages()` method:

```php
try {
	$user->store($database['default']);

} catch (Dotink\Dub\ValidationException) {
	printf(
		'Invalid fields: %s',
		join(', ', array_keys($user->fetchValidationMessages()))
	);
}
```