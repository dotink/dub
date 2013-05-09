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

### Persisting a Model

```php
$databases['default']->persist($user);
$databases['default']->flush();
```