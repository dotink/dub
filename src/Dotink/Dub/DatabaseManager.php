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