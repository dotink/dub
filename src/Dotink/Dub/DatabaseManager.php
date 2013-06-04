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
		 * Lists of configs keyed by primary database name
		 *
		 * @access private
		 * @var array
		 */
		private $configs = array();


		/**
		 * Lists of connections keyed by primary database name
		 *
		 * @access private
		 * @var array
		 */
		private $connections = array();


		/**
		 * List of namespaces keyed by primary database name
		 *
		 * @access private
		 * @var array
		 */
		private $namespaces = array();


		/**
		 * Whether or not this database manager is in development mode
		 *
		 * @access private
		 * @var boolean
		 */
		private $developmentMode = FALSE;


		/**
		 * A map of entity classes currently associated with databases
		 *
		 * @access private
		 * @var array
		 */
		private $map = array();


		/**
		 * Adds a new connection with a simple database name
		 *
		 * @access public
		 * @param string $name The database name
		 * @param array $connection The connection parameters (see doctrine 2)
		 * @param string $proxy_path The path to use for proxies, if NULL system temp dir is used
		 * @return void
		 */
		public function add($name, Array $connection, $namespace = NULL, $proxy_path = NULL)
		{
			$config = new Configuration();
			$cache  = $this->developmentMode
				? new Cache\ArrayCache()
				: new Cache\ApcCache();

			$config->setMetadataCacheImpl($cache);
			$config->setQueryCacheImpl($cache);
			$config->setProxyNamespace($namespace ? ($namespace . '\\Proxies') : 'Proxies');
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

			if (($existing_database = array_search($namespace, $this->namespaces)) !== FALSE) {
				throw new Flourish\ProgrammerException(
					'Database %s already registered for the %s namespace',
					$existing_database,
					$namespace ? $namespace : 'global'
				);
			}

			$this->offsetSet($name, EntityManager::create($connection, $config));

			$this->connections[$name] = $connection;
			$this->configs[$name]     = $config;
			$this->namespaces[$name]  = $namespace;

		}


		/**
		 * Creates the schema from mapped classes or class/classes specified
		 *
		 * @access public
		 * @param string $database A database name
		 * @param array $classes A list of classes to create schemas for, uses map if not specified
		 * @return void
		 */
		public function createSchema($database, $classes = array())
		{
			$this->validateDatabase($database);

			$schema_tool = new SchemaTool($this[$database]);
			$classes     = $this->resolveClasses($database, $classes);

			$schema_tool->createSchema($this->fetchMetaData($database, $classes));
		}


		/**
		 * Drops the schema of mapped classes or class/classes specified
		 *
		 * @access public
		 * @param string $database A database name
		 * @param array $classes A list of classes to create schemas for, uses map if not specified
		 * @return void
		 */
		public function dropSchema($database, $classes = array())
		{
			$this->validateDatabase($database);

			$schema_tool = new SchemaTool($this[$database]);
			$classes     = $this->resolveClasses($database, $classes);

			$schema_tool->dropSchema($this->fetchMetaData($database, $classes));
		}


		/**
		 * Find a database name by namespace
		 *
		 * @access public
		 * @param string $namespace The namespace with which to lookup a database name
		 * @return string The primary database name for that namespace, NULL if it doesn't exist
		 */
		public function lookup($namespace)
		{
			$database_name = array_search($namespace, $this->namespaces);

			return ($database_name !== FALSE)
				? $database_name
				: NULL;
		}


		/**
		 * Maps a class to a database
		 *
		 * @access public
		 * @param string $database A database name
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
		 * Resets the database by creating a new instance with the same connection/config
		 *
		 * @access public
		 * @param string $database A database name
		 * @return void
		 */
		public function reset($database)
		{
			$this->validateDatabase($database);

			if ($this->offsetExists($database)) {
				$this->offsetUnset($database);

				$connection = $this->connections[$database];
				$config     = $this->configs[$database];

				$this->offsetSet($database, EntityManager::create($connection, $config));
			}
		}


		/**
		 * Retrieve a database object by namespace
		 *
		 * @access public
		 * @param string $namespace The namespace with which to retrieve a database object
		 * @return EntityManager The database associated with the namespace
		 * @throws Flourish\ProgrammerException If no database is registered with that namespace
		 */
		public function retrieve($namespace)
		{
			if (!($database_name = $this->lookup($namespace))) {
				throw new Flourish\ProgrammerException(
					'Cannot retrieve database for namespace %s, none found',
					$namespace
				);
			}

			return $this[$database_name];
		}


		/**
		 * Sets the database manager to development mode
		 *
		 * @access public
		 * @return void
		 */
		public function setDevelopmentMode()
		{
			$this->developmentMode = TRUE;
		}


		/**
		 * Updates the schema for a given database
		 */
		public function updateSchema($database, $classes = array())
		{
			$this->validateDatabase($database);

			$schema_tool = new SchemaTool($this[$database]);
			$classes     = $this->resolveClasses($databse, $classes);

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
		private function resolveClasses($database, $classes = array())
		{
			if (!count($classes) && isset($this->map[$database])) {
				$classes = $this->map[$database];
			}

			foreach ($classes as $class) {
				if (!class_exists($class)) {
					Model::create($class, TRUE);
				}
			}

			return $classes;
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