<?php namespace Dotink\Dub
{
	use ArrayObject;
	use Dotink\Flourish;
	use Doctrine\Common\Cache;
	use Doctrine\ORM\UnitOfWork;
	use Doctrine\ORM\EntityManager;
	use Doctrine\ORM\Configuration;
	use Doctrine\ORM\Tools\SchemaTool;

	class DatabaseManager extends ArrayObject
	{
		static private $developmentMode = FALSE;

		private $map = array();


		/**
		 *
		 */
		static public function setDevelopmentMode()
		{
			self::$developmentMode = TRUE;
		}


		/**
		 *
		 */
		public function add($alias, Array $connection, $proxy_path = NULL)
		{
			$config = new Configuration();
			$cache  = self::$developmentMode
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

			if (self::$developmentMode) {
				$config->setAutoGenerateProxyClasses(TRUE);
			} else {
				$config->setAutoGenerateProxyClasses(FALSE);
			}

			$this->offsetSet($alias, EntityManager::create($connection, $config));
		}


		/**
		 *
		 */
		public function createSchema($database, $classes = array())
		{
			$this->validateDatabase($database);

			settype($classes, 'array');

			$meta_data   = array();
			$schema_tool = new SchemaTool($this[$database]);

			if (!count($classes) && isset($this->map[$database])) {
				$classes = $this->map[$database];
			}

			foreach ($classes as $class) {
				$meta_data[] = $this[$database]->getClassMetadata($class);
			}

			$schema_tool->createSchema($meta_data);
		}


		/**
		 *
		 */
		public function updateSchema($database, $classes = array())
		{
			$this->validateDatabase($database);

			settype($classes, 'array');

			$meta_data   = array();
			$schema_tool = new SchemaTool($this[$database]);

			if (!count($classes) && isset($this->map[$database])) {
				$classes = $this->map[$database];
			}

			foreach ($classes as $class) {
				$meta_data[] = $this[$database]->getClassMetadata($class);
			}

			$schema_tool->updateSchema($meta_data);
		}


		/**
		 *
		 */
		public function isDetached($database, Model $entity)
		{
			$this->validateDatabase($database);

			$state = $this[$database]->getUnitOfWork()->getEntityState($entity);

			return $state == UnitOfWork::STATE_DETACHED;
		}


		/**
		 *
		 */
		public function isManaged($database, Model $entity)
		{
			$this->validateDatabase($database);

			$state = $this[$database]->getUnitOfWork()->getEntityState($entity);

			return $state == UnitOfWork::STATE_MANAGED;
		}


		/**
		 *
		 */
		public function isNew($database, Model $entity)
		{
			$this->validateDatabase($database);

			$state = $this[$database]->getUnitOfWork()->getEntityState($entity);

			return $state == UnitOfWork::STATE_NEW;
		}


		/**
		 *
		 */
		public function isRemoved($database, Model $entity)
		{
			$this->validateDatabase($database);

			$state = $this[$database]->getUnitOfWork()->getEntityState($entity);

			return $state == UnitOfWork::STATE_REMOVED;
		}


		/**
		 *
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