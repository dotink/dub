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
		 *
		 */
		private $configs = array();


		/**
		 *
		 */
		private $connections = array();


		/**
		 *
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
		 * @param string $name The database name (primary alias)
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

			if (isset($this->namespaces[$namespace])) {
				throw new Flourish\ProgrammerException(
					'Database %s already registered for the %s namespace',
					$this->namespaces[$namespace],
					$namespace ? $namespace : 'global'
				);
			}

			$this->offsetSet($name, EntityManager::create($connection, $config));

			$this->connections[$name]     = $connection;
			$this->configs[$name]         = $config;
			$this->namespaces[$namespace] = $name;

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
		 *
		 */
		public function lookup($namespace)
		{
			return isset($this->namespaces[$namespace])
				? $this[$this->namespaces[$namespace]]
				: NULL;
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