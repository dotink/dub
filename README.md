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

### Configuring a Model

```php
Model::configure('Dotink\Test\User', [
	'pkey' => 'id',
	'fields' => [
		'id' => ['type' => 'serial']
	]
]);
```

Mapping it to a different repository/table:

```php
Model::configure('Dotink\Test\User', [
	'repo' => 'Users'
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