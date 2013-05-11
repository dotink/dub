<?php namespace Dotink\Dub
{
	use ArrayObject;
	use Dotink\Flourish;
	use Doctrine\Common\Cache;
	use Doctrine\ORM\EntityManager;
	use Doctrine\ORM\Configuration;
	use Doctrine\ORM\Tools\SchemaTool;

	class DatabaseManager extends ArrayObject
	{
		/**
		 * An array of database aliases
		 *
		 * @access private
		 * @var array
		 */
		private $aliases = array();


		/**
		 * Whether or not this database manager is in development mode
		 *
		 * @access private
		 * @var boolean
		 */
		private $developmentMode = FALSE;


		/**
		 * A map of classes currently associated with aliased databases
		 *
		 * @access private
		 * @var array
		 */
		private $map = array();


		/**
		 * Adds a new connection under an initial alias
		 *
		 * @access public
		 * @param string $alias The alias for the connection
		 * @param array $connection The connection parameters (see doctrine 2)
		 * @param string $proxy_path The path to use for proxies, if NULL system temp dir is used
		 * @return void
		 */
		public function add($alias, Array $connection, $proxy_path = NULL)
		{
			$config = new Configuration();
			$cache  = $this->developmentMode
				? new Cache\ArrayCache()
				: new Cache\ApcCache();

			$config->setMetadataCacheImpl($cache);
			$config->setQueryCacheImpl($cache);
			$config->setProxyNamespace('Dub\Proxies');
			$config->setProxyDir(!isset($proxy_path)
				? sys_get_temp_dir()
				: $proxy_path
			);

			$config->setMetadataDriverImpl(new Driver());

			if ($this->developmentMode) {
				$config->setAutoGenerateProxyClasses(TRUE);
			} else {
				$config->setAutoGenerateProxyClasses(FALSE);
			}

			$this->offsetSet($alias, EntityManager::create($connection, $config));
		}


		/**
		 * Aliases a database to another
		 *
		 * @access public
		 * @param string $database The initial alias used when adding the DB
		 * @param string $alias The alias to allow it to be referenced as
		 * @return void
		 */
		public function alias($database, $alias)
		{

		}


		/**
		 * Creates the schema from mapped classes or class/classes specified
		 *
		 * @access public
		 * @param string $database A database alias
		 * @param array $classes A list of classes to create schemas for, uses map if not specified
		 * @return void
		 */
		public function createSchema($database, $classes = array())
		{
			$this->validateDatabase($database);

			$schema_tool = new SchemaTool($this[$database]);

			if (!count($classes) && isset($this->map[$database])) {
				$classes = $this->map[$database];
			}

			foreach ($classes as $class) {
				Model::create($class, TRUE);
			}

			$schema_tool->createSchema($this->fetchMetaData($database, $classes));
		}


		/**
		 * Maps a class to a database
		 *
		 * @access public
		 * @param string $database A database alias
		 * @param string $class The class to map to the database
		 * @return void
		 */
		public function map($database, $class)
		{
			$this->validateDatabase($database);

			if (!isset($this->map[$database])) {
				$this->map[$database] = array();
			}

			if (array_search($class, $this->map[$database]) === FALSE) {
				$this->map[$database][] = $class;
			}
		}


		/**
		 *
		 */
		public function reflectSchema($database, $repo = NULL)
		{
			$this->validateDatabase($database);

			$connection = $this[$database]->getConnection();
			$schema     = $connection->getSchemaManager();
			$data       = array();

			if (!$repo) {
				foreach ($schema->listTables() as $table) {
					$repo        = $table->getname();
					$data[$repo] = $this->reflectSchema($database, $repo);
				}

			} else {
				$columns = $schema->listTableColumns($repo);
				$indexes = $schema->listTableIndexes($repo);

				$data['repo']     = $repo;
				$data['fields']   = array();
				$data['defaults'] = array();

				foreach ($columns as $column) {
					$name     =  $column->getName();
					$type     =  $column->getType();
					$default  =  $column->getDefault();
					$length   =  $column->getLength();
					$scale    =  $column->getScale();
					$nullable = !$column->getNotNull();

					if (strpos($name, '_') !== FALSE) {
						$field = Flourish\Text::create($name)->camelize()->compose();
					} else {
						$field = $name;
					}

					$data['fields'][$field] = ['type' => $type->getName()];

					switch (get_class($type)) {
						case 'Doctrine\DBAL\Types\IntegerType':
							if ($column->getAutoIncrement()) {
								$data['fields'][$field]['type'] = 'serial';
							}
							break;

						case 'Doctrine\DBAL\Types\StringType':
							if ($length) {
								$data['fields'][$field]['length'] = $length;
							}
							break;
						case 'Doctrine\DBAL\Types\DateTimeType':
							if ($default) {
								if ($default == 'now()' || $default = 'CURRENT_DATETIME') {
									$data['defaults'][$field] = 'DateTime';
								}
							}
							break;
					}

					if (!$nullable) {
						$data['fields'][$field]['nullable'] = FALSE;
					}

					if (!isset($data['defaults'][$field])) {
						$data['defaults'][$field] = $default;
					}
				}

				foreach ($indexes as $index) {
					if ($index->isPrimary()) {
						$data['pkey'] = array();

						foreach ($index->getColumns() as $column) {
							if (strpos($column, '_') !== FALSE) {
								$field = Flourish\Text::create($column)->camelize()->compose();
							} else {
								$field = $column;
							}

							$data['pkey'][] = $field;
						}
					}
				}
			}

			return $data;
		}


		/**
		 *
		 */
		public function setDevelopmentMode()
		{
			$this->developmentMode = TRUE;
		}


		/**
		 *
		 */
		public function updateSchema($database, $classes = array())
		{
			$this->validateDatabase($database);

			$schema_tool = new SchemaTool($this[$database]);

			$schema_tool->updateSchema($this->fetchMetaData($database, $classes));
		}


		/**
		 *
		 */
		private function fetchMetaData($database, $classes)
		{
			settype($classes, 'array');

			$meta_data = array();

			if (!count($classes) && isset($this->map[$database])) {
				$classes = $this->map[$database];
			}

			foreach ($classes as $class) {
				$meta_data[] = $this[$database]->getClassMetadata($class);
			}

			return $meta_data;
		}


		/**
		 *
		 */
		private function validateDatabase($database)
		{
			if (!isset($this[$database])) {
				throw new Flourish\ProgrammerException(
					'Database %s is not registered with the database manager',
					$database
				);
			}
		}
	}
}