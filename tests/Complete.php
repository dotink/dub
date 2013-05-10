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

			Model::configure('Dotink\Lab\User', [
				'pkey' => 'id',
				'fields' => [
					'id'           => ['type' => 'serial'],
					'emailAddress' => ['nullable' => FALSE, 'unique' => TRUE],
					'dateCreated'  => ['type' => 'datetime']
				]
			]);

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

				assert($shared->databases->isNew('default', $user))
					-> equals(TRUE)
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

				assert($shared->databases->isManaged('default', $user))
					-> equals(TRUE)
				;
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

				assert('Dotink\Lab\User::$name')
					-> using($user)
					-> equals('Matthew J. Sahagian')
				;

				assert('Dotink\Lab\User::$dateCreated')
					-> using($user)
					-> isInstanceOf('DateTime')
				;
			},

			'Delete Model' => function($data, $shared) {
				$user = $shared->databases['default']->find('Dotink\Lab\User', 1);

				$user->remove($shared->databases['default']);

				assert($shared->databases->isRemoved('default', $user))
					-> equals(TRUE)
				;

				$shared->databases['default']->flush();

				assert($shared->databases->isNew('default', $user))
					-> equals(TRUE)
				;
			},
		],

		'cleanup' => function($data, $shared) {
			unlink($shared->testDB);
		}

	];
}