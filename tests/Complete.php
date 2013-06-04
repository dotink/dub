<?php namespace Dotink\Lab
{
	use User;
	use Profile;
	use Group;
	use Division;
	use Dotink\Dub\Model;
	use Dotink\Dub\ModelConfiguration;
	use Dotink\Dub\DatabaseManager;

	return [


		/**
		 *
		 */
		'setup' => function($data, $shared) {
			$shared->testDB    = __DIR__ . DS . 'workspace' . DS . 'test.db';
			$shared->databases = new DatabaseManager();
			$shared->databases->add('default', [
				'driver' => 'pdo_pgsql',
				'host'   => 'localhost',
				'dbname' => 'test',
				'user'   => 'postgres'
			]);

			ModelConfiguration::store('Group', [
				'fields' => [
					'id' => ['type' => 'serial']
				],

				'primary' => 'id'
			]);

			ModelConfiguration::store('Division', [
				'fields' => [
					'id' => ['type' => 'serial']
				],

				'primary' => 'id'
			]);

			ModelConfiguration::store('Profile', [
				'fields' => [
					'id' => ['type' => 'serial']
				],

				'primary' => 'id'
			]);


			ModelConfiguration::store('User', [
				'fields' => [
					'id'           => ['type' => 'serial'],
					'name'         => ['type' => 'string'],
					'emailAddress' => ['type' => 'string', 'nullable' => FALSE, 'unique' => TRUE],
					'dateCreated'  => ['type' => 'datetime', 'default' => '+DateTime()'],

					//
					// Many-to-Many
					//

					'groups' => [
						'references' => 'Group', 'via' => [
							'repo'  => 'user_groups',
							'local' => ['user_id'  => 'id'],
							'pivot' => ['group_id' => 'id']
						]
					],

					//
					// One-to-One
					//

					'profile' => [
						'references' => 'Profile', 'unique' => TRUE, 'via' => [
							'local' => ['profile' => 'id'],
							'unique' => TRUE
						]
					],

					//
					// Many-to-One
					//

					'divisions' => [
						'references' => 'Division', 'unique' => TRUE, 'via' => [
							'local' => ['division' => 'id']
						]
					]

				],

				'primary' => 'id'
			]);
		},


		/**
		 *
		 */
		'tests' => [


			//
			//
			//
			'Create Schema' => function($data, $shared) {
				$shared->databases->map('default', 'User');
				$shared->databases->map('default', 'Group');
				$shared->databases->map('default', 'Division');
				$shared->databases->map('default', 'Profile');

				$shared->databases->createSchema('default');
			},


			//
			//
			//
			'Basic Model' => function($data, $shared) {
				$user = new User();
				$user->setName('Matthew J. Sahagian');

				assert('User::$name')
					-> using($user)
					-> equals('Matthew J. Sahagian')
				;
			},


			//
			//
			//
			'Get Model Status' => function($data, $shared) {
				$user = Model::create('User');
				$user->setName('Matthew J. Sahagian');

				assert('User::isNew')
					-> using  ($user)
					-> with   ($shared->databases['default'])
					-> equals (TRUE)
				;
			},


			//
			//
			//
			'Store Model' => function($data, $shared) {
				$user    = new User();
				$profile = new Profile();

				$user->setName('Matthew J. Sahagian');
				$user->setEmailAddress('info@dotink.org');
				$user->setProfile($profile);

				$shared->databases['default']->persist($user);
				$shared->databases['default']->persist($profile);

				$user2 = new User();
				$user2->populate([
					'name'          => 'Jane Doe',
					'email_address' => 'info@example.com'
				]);

				$user2->store($shared->databases['default']);

				$shared->databases['default']->flush();

				assert('User::isManaged')
					-> using  ($user)
					-> with   ($shared->databases['default'])
					-> equals (TRUE)
				;
			},


			//
			//
			//
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


			//
			//
			//
			'Read Model' => function($data, $shared) {

				//
				// We previously stored an entity, now we want to sleep 5 seconds and call it
				// back to see where it's date created field is
				//

				sleep(5);

				$user = $shared->databases['default']->find('User', 1);

				reject($user->getDateCreated()->format('U'))
					-> is (GT, time() - 5)
				;

				assert('User::$name')
					-> using($user)
					-> equals('Matthew J. Sahagian')
				;

				$user = $shared->databases['default']->find('User', 2);

				assert('User::$name')
					-> using($user)
					-> equals('Jane Doe')
				;
			},


			//
			//
			//
			'Update Model' => function($data, $shared) {
				$user = $shared->databases['default']->find('User', 1);

				$user->setName('Matthew Sahagian');
				$user->store($shared->databases['default']);

				$shared->databases['default']->flush();

				assert('User::$name')
					-> using($user)
					-> equals('Matthew Sahagian')
				;
			},


			//
			//
			//
			'Delete Model' => function($data, $shared) {
				$user = $shared->databases['default']->find('User', 1);

				$user->remove($shared->databases['default']);

				assert('User::isRemoved')
					-> using  ($user)
					-> with   ($shared->databases['default'])
					-> equals (TRUE)
				;

				$shared->databases['default']->flush();

				assert('User::isNew')
					-> using  ($user)
					-> with   ($shared->databases['default'])
					-> equals (TRUE)
				;
			},


			//
			//
			//
			'Schema Reflection' => function($data, $shared) {
				ModelConfiguration::reflect('Person', $shared->databases['default'], 'users');

				$person = Model::create('Person');
				$person->setName('John Doe');
				$person->setEmailAddress('person@example.com');

				$shared->databases['default']->flush();
			}
		],


		/**
		 *
		 */
		'cleanup' => function($data, $shared) {
			$shared->databases->dropSchema('default');
		}

	];
}