<?php namespace Dotink\Lab
{
	use Dotink\Dub\Model;
	use Dotink\Dub\DatabaseManager;

	return [
		'setup' => function($data, $shared) {
			needs('vendor/autoload.php');

			$shared->testDB    = __DIR__ . DS . 'workspace' . DS . 'test.db';
			$shared->databases = new DatabaseManager();
			$shared->databases->add('default', [
				'driver' => 'pdo_sqlite',
				'path' => $shared->testDB
			]);

			spl_autoload_register('Dotink\Dub\Model::dynamicLoader');

			Model::configure('Dotink\Lab\User', [
				'pkey' => 'id',
				'fields' => [
					'id'           => ['type' => 'serial'],
					'name'         => ['type' => 'string'],
					'emailAddress' => ['type' => 'string', 'nullable' => FALSE, 'unique' => TRUE],
					'dateCreated'  => ['type' => 'datetime']
				],
				'defaults' => [
					'dateCreated' => 'DateTime'
				]
			]);

/*
			class User extends Model
			{
				protected $id = NULL;
				protected $name = NULL;
				protected $emailAddress = NULL;
				protected $dateCreated = NULL;
				public function __construct()
				{
					$this->dateCreated = new \DateTime();
				}
			}
*/
		},

		'tests' => [
			'Create Schema' => function($data, $shared) {
				$shared->databases->map('default', 'Dotink\Lab\User');
				$shared->databases->createSchema('default');
			},

			'Basic Model' => function($data, $shared) {
				$user = new User();
				$user->setName('Matthew J. Sahagian');

				assert('Dotink\Lab\User::$name')
					-> using($user)
					-> equals('Matthew J. Sahagian')
				;

				assert('Dotink\Lab\User::isNew')
					-> using  ($user)
					-> with   ($shared->databases['default'])
					-> equals (TRUE)
				;
			},

			'Store Model' => function($data, $shared) {
				$user = new User();
				$user->setName('Matthew J. Sahagian');
				$user->setEmailAddress('info@dotink.org');

				$shared->databases['default']->persist($user);

				$user2 = new User();
				$user2->populate([
					'name'          => 'Jane Doe',
					'email_address' => 'info@example.com'
				]);

				$user2->store($shared->databases['default']);

				$shared->databases['default']->flush();

				assert('Dotink\Lab\User::isManaged')
					-> using  ($user)
					-> with   ($shared->databases['default'])
					-> equals (TRUE)
				;

				sleep(5);
			},

			'Store NULL on non-Nullable' => function($data, $shared) {
				$user = new User();
				$user->setName('John Smith');

				assert(function() use ($shared, $user) {

					//
					// We will attempt to insert a record which violates the nullable
					// constraint and make sure it throws an exception.
					//

					$shared->databases['default']->persist($user);
					$shared->databases['default']->flush();

				})->throws('Dotink\Dub\ValidationException');

				assert($user->fetchValidationMessages())
					-> has ('emailAddress');
			},
/*
			'Store Non-Unique' => function($data, $shared) {
				assert(function() use ($shared) {

					//
					// We will attempt to insert a record which violates the unique
					// constraint and make sure it throws an exception.
					//

					$user = new User();
					$user->setEmailAddress('info@example.com');

					$shared->databases['default']->persist($user);
					$shared->databases['default']->flush();

				})->throws('Doctrine\ORM\ORMException');
			},
*/
			'Read Model' => function($data, $shared) {
				$user = $shared->databases['default']->find('Dotink\Lab\User', 1);

				reject($user->getDateCreated()->format('U'))
					-> is (GTE, time() - 5)
				;

				assert('Dotink\Lab\User::$name')
					-> using($user)
					-> equals('Matthew J. Sahagian')
				;
			},

			'Update Model' => function($data, $shared) {
				$user = $shared->databases['default']->find('Dotink\Lab\User', 1);

				$user->setName('Matthew Sahagian');
				$user->store($shared->databases['default']);

				assert('Dotink\Lab\User::$name')
					-> using($user)
					-> equals('Matthew Sahagian')
				;
			},

			'Delete Model' => function($data, $shared) {
				$user = $shared->databases['default']->find('Dotink\Lab\User', 1);

				$user->remove($shared->databases['default']);

				assert('Dotink\Lab\User::isRemoved')
					-> using  ($user)
					-> with   ($shared->databases['default'])
					-> equals (TRUE)
				;

				$shared->databases['default']->flush();

				assert('Dotink\Lab\User::isNew')
					-> using  ($user)
					-> with   ($shared->databases['default'])
					-> equals (TRUE)
				;
			},
		],

		'cleanup' => function($data, $shared) {
			unlink($shared->testDB);
		}

	];
}