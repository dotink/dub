## A Simple Doctrine 2 Wrapper

Dub is is a set of classes designed to ease Doctrine 2 development.  It uses simple conventions and a bit of magic to limit the amount of bootstrap code you need to write.  It can also create models dynamically using a simple array configuration or by mapping to a database table.

### Model Configuration

Models in Dub are configuration driven.

```php
namespace Dotink\Dub;

ModelConfiguration::store('User', [
	'fields' => [
		'id'           => ['type' => 'serial', 'nullable' => FALSE],
		'name'         => ['type' => 'string', 'nullable' => FALSE],
		'status'       => ['type' => 'string', 'nullable' => FALSE, 'default' => 'Active'],

		//
		// Many-to-Many
		//

		'groups' => [
			'references' => 'Group', 'via' => [
				'repo'  => 'user_groups',
				'local' => ['user'  => 'id'],
				'pivot' => ['group' => 'id']
			]
		],

		//
		// One-to-One
		//

		'profile' => [
			'references' => 'Profile', 'unique' => TRUE, 'on_delete' => 'CASCADE', 'via' => [
				'local' => ['profile' => 'id'],
				'unique' => TRUE
			]
		],

		//
		// One-to-Many
		//

		'emailAddresses' => [
			'references' => 'EmailAddress', 'on_delete' => 'CASCADE', 'via' => [
				'local' => ['id' => 'user'],
				'unique' => TRUE
			]
		]
	],

	'primary' => 'id'
]);


ModelConfiguration::store('Division', [
	'fields' => [
		'id'   => ['type' => 'serial', 'nullable' => FALSE],
		'name' => ['type' => 'string', 'nullable' => FALSE],

		//
		// One-to-Many
		//

		'users' => [
			'references' => 'User', 'remove_orphans' => TRUE
		]
	],

	'primary' => 'id'
]);
```

#### Configuration Schema

Although there are many similarities, the configuration schema for Dub models differs from Doctrine 2 in a number of ways.  Let's look at some of basic field definitions to get an idea.

Specifying a type of `serial` will actually map a field to an integer, however, it will automatically establish autogeneration/autoincrement on that field.

```php
'fields' => [
	'id' => ['type' => 'serial', 'nullable' => FALSE]
]
```

A field with an unspecified type will default to string.  The type definition below is unnecessary:

```php
'fields' => [
	'emailAddress' => ['type' => 'string']
]
```

You can specify the column/property which a field maps to in a database using the `data_name` key on the field configuration.

```php
'fields' => [
	'emailAddress' => ['data_name' => 'email']
]
```

The default field-to-data name convention is converting the field to underscore.  The `data_name` field below is not necessary.

```php
'fields' => [
	'emailAddress' => ['data_name' => 'email_address']
]
```

You can define default values through configuration.

```php
'fields' => [
	'status' => ['default' => 'Active']
]
```

You can also set defaults that instantiate classes (assuming no arguments are necessary):

```php
'fields' => [
	'dateCreated' => ['default' => '+DateTime()']
]
```

Other than that, field configuration is pretty similar to what is available through doctrine.  Including the following:

- nullable
- unique
- length (for strings)

```php
'fields' => [
	'emailAddress' => ['length' => 256, 'nullable' => FALSE, 'unique' => TRUE]
]
```

### Concrete vs. Dynamic Models

While Doctrine 2 emphasizes the creation of concrete model, unless you're using annotations, the creation of even a medium sized model can be a lot of initial work.  Scaffolders, IDE-based code generation, and some internal doctrine methods can help with this, but it can take time to understand.

In Dub, any configured model can be created without the need for a concrete class using the `Model::create()` method:

```php
$user = Model::create('User');
```

The base `Model` class will provide magic getters and setters for all fields defined in a configuration:

```php
$user->setEmailAddress('info@dotink.org');

if ($user->getEmailAddress() == 'info@dotink.org') {
	echo 'It worked!';
}
```

You can also use the populate method to set a number of fields at once.  This does not bypass the `set*()` call.

```php
$user->populate([
	'name'          => 'John Doe',
	'email_address' => 'john.d@example.com'
]);
```

If you opt to create a semi-concrete model, you need only to initially define the fields.  All fields should be represented as protected properties:

```php
class User extends Model
{
	protected $id = NULL;
	protected $name = NULL;
	protected $emailAddress = NULL;
	protected $status = NULL;
}
```

You can overload getters and setters by creating standard methods:

```php
public function setEmailAddress($email)
{
	$this->emailAddress = str_replace('@', ' [at] ', $email);
}

public function resolveEmailAddress($email)
{
	$this->emailAddress = str_replace(' [at] ', '@', $this->getEmailAddress())
}
```

In the above example, get is still dynamic.

### Creating a Database

```php
$databases  = new Dotink\Dub\DatabaseManager();
$connection = [
	'driver' => 'pdo_sqlite',
	'path'   => 'test.db'
];

$databases->add('default', $connection);
```

If you add multiple databases you will need to namespace additional ones added.  The default namespace is assumed global.  Namespacing is use for the Proxy namespace, but can also be used to look-up databases.

```php
$databases->add('forums', $connection, 'Dotink\Forums');
```

You can lookup a database via its namespace with the `lookup()` method.  This will return the associated alias *not* the entity manager itself.

```
namespace Dotink\Forums;

$namespace = __NAMESPACE__;
$database  = $databases[$databases->lookup($namespace)];
$post      = $database->find($namespace . '\Post', 1);

$post->setTitle('This is the first post');
$post->store($database);
```

### Writing the Schema

```php
$databases->createSchema('default', ['User']);
```

You can also map models 1-by-1 and then create the schema for an entire database:

```php
$databases->map('default', 'User');
$databases->map('default', 'Article');
$databases->map('default', 'Contact');

$database->createSchema('default');
```

### Persisting or updating a Model

```php
$user->store($databases['default']);
```

You still need to flush to make it stick:

```php
$databases['default']->flush();
```

### Removing a Model

```php
$user->remove($databases['default']);
```

### Database mapping

As previously mentioned, the `data_name` field in a configuration is used to map a field to the appropriate column or property in a database.  The convention for this is to convert your field name to an underscore snake format.

Similarly, you can define the `repo` element to configure a table/collection:

```php
ModelConfiguration::store('User', [
	'repo'   => 'Forum_Users'
	'fields' => [
		...
	],
	...
]);
```

The default convention for a repo is the pluralized underscore/snake format of the class's short name, so a model with a shorname of `User` would be `users`.

You can also configure models from existing tables on databases:

```php
ModelConfiguration::reflect('User', $databases['default']);
```

This will use the standard repo convention, but you can specify a repository with an alternative name as well:

```php
ModelConfiguration::reflect('User', $databases['default'], 'Forum_Users');
```

## Notes

Some deficiencies of Doctrine 2 discovered while working on this:

- Support exists for `onDelete` for associations, but not `onUpdate`
- Explicitly defined unique keys for one-to-one associations will be replaced with ugly names
- Identifiers are not quoted (beware reserved words for tables/columns)
