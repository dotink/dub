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
					'id' => ['type' => 'serial']
				]
			]);

			class User extends Model
			{
				protected $id = NULL;
				protected $name = NULL;
			}
		},

		'tests' => [
			'Create Schema' => function($data, $shared) {
				$shared->databases->map('Dotink\Lab\User', 'default');
				$shared->databases->createSchema('default');
			},

			'Basic Model' => function($data, $shared) {
				$user = new User();
				$user->setName('Matthew J. Sahagian');

				assert('Dotink\Lab\User::$name')
					-> using($user)
					-> equals('Matthew J. Sahagian')
				;
			},

			'Store Model' => function($data, $shared) {
				$user = new User();
				$user->setName('Matthew J. Sahagian');

				$shared->databases['default']->persist($user);
				$shared->databases['default']->flush();
			},

			'Read Model' => function($data, $shared) {
				$user = $shared->databases['default']->find('Dotink\Lab\User', 1);

				assert('Dotink\Lab\User::$name')
					-> using($user)
					-> equals('Matthew J. Sahagian')
				;
			}
		],

		'cleanup' => function($data, $shared) {
			unlink($shared->testDB);
		}

	];
}