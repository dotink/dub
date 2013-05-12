<?php namespace Dotink\Dub
{
	use Dotink\Flourish;
	use Doctrine\ORM\EntityManager;

	/**
	 *
	 */
	class ModelConfiguration
	{
		const DEFAULT_FIELD_TYPE = 'string';

        /**
         *
         */
        static private $configs = array();


		/**
		 *
		 */
		static private $fields = array();


		/**
		 *
		 */
		static private $primaries = array();


		/**
		 *
		 */
		static private $types = array();


		/**
		 *
		 */
		static private $dataNames = array();


		/**
		 *
		 */
		static private $options = array();


		/**
		 *
		 */
		static private $ukeys = array();


		/**
		 *
		 */
		static private $indexes = array();


		/**
		 *
		 */
		static private $repositories = array();


		/**
		 *
		 */
		static private $nullable = array();


		/**
		 *
		 */
		static private $defaults = array();


		/**
		 *
		 */
		private $class = NULL;


		/**
		 *
		 */
		private $shortName = NULL;


		/**
		 *
		 */
		private $namespace = NULL;


        /**
         *
         */
        static public function load($class, $action = 'get configuration')
        {
            if (!isset(self::$configs[$class])) {
                throw new Flourish\EnvironmentException(
                    'Cannot %s, configuration for class %s is not found',
                    $action,
                    $class
                );
            }

            return self::$configs[$class];
        }


		/**
		 *
		 */
		static public function makeDataName($field)
		{
			return Flourish\Text::create($field)
				-> underscorize()
				-> compose();
		}


		/**
		 *
		 */
		static public function makeField($data_name)
		{
			return strpos($data_name, '_') !== FALSE
				? Flourish\Text::create($data_name)->camelize()->compose()
				: $data_name;
		}


		/**
		 *
		 */
		static public function makeRepositoryName($short_name)
		{
			return Flourish\Text::create($short_name)
				-> underscorize()
				-> pluralize()
				-> compose();
		}


        /**
         *
         */
        static public function store($class, Array $config)
        {
            self::$configs[$class] = new self($class, $config);
        }


        /**
         *
         */
        static public function reflect($class, EntityManager $database, $repo = NULL)
        {
			$connection = $database->getConnection();
			$schema     = $connection->getSchemaManager();

			if (!$repo) {
				$class_parts = explode('\\', $class);
				$short_name  = array_pop($class_parts);
				$repo        = self::makeRepositoryName($short_name);
			}

			$columns = $schema->listTableColumns($repo);
			$indexes = $schema->listTableIndexes($repo);
			$config  = [
				'repo'    => $repo,
				'fields'  => array(),
				'primary' => array(),
				'unique'  => array(),
				'indexes' => array()
			];

			foreach ($columns as $column) {
				$name     =  $column->getName();
				$type     =  $column->getType();
				$default  =  $column->getDefault();
				$length   =  $column->getLength();
				$scale    =  $column->getScale();
				$nullable = !$column->getNotNull();
				$field    = self::makeField($name);

				$config['fields'][$field]['type'] = $type->getName();

				//
				// Add or modify special types/options
				//

				switch ($config['fields'][$field]['type']) {
					case 'integer':
						if ($column->getAutoIncrement()) {
							$config['fields'][$field]['type'] = 'serial';
						}
						break;

					case 'string':
						if ($length) {
							$config['fields'][$field]['length'] = $length;
						}
						break;
				}

				//
				// Configure nullable
				//

				if (!$nullable) {
					$config['fields'][$field]['nullable'] = FALSE;
				}

				//
				// Add defaults
				//

				if ($default !== NULL) {
					$config['fields'][$field]['default'] = $default;
				}

				switch ($config['fields'][$field]['type']) {
					case 'datetime':
						if ($default == 'now()' || $default = 'CURRENT_DATETIME') {
							$config['fields'][$field]['default'] = '+DateTime()';
						}
						break;
				}
			}

			//
			// Set up indexes
			//

			foreach ($indexes as $index) {
				if ($index->isPrimary()) {
					foreach ($index->getColumns() as $data_name) {
						$config['primary'][] = self::makeField($data_name);
					}

				} else {
					$fields = array();

					foreach ($index->getColumns() as $data_name) {
						$fields[] = self::makeField($data_name);
					}

					$container = $index->isUnique() ? 'unique' : 'indexes';
					$idx_name  = $index->getName();

					$config[$container][$idx_name] = $fields;
				}
			}


			self::store($class, $config);
		}


		/**
		 *
		 */
		public function __construct($class, Array $config)
		{
			$class_parts     = explode('\\', $class);
			$this->class     = $class;
			$this->shortName = array_pop($class_parts);
			$this->namespace = implode('\\', $class_parts);

			$this->parse($config);
		}


		/**
		 *
		 */
		public function get($data = NULL)
		{
			if ($data) {
				return isset($this->$data)
					? $this->$data
					: NULL;
			}

			$config = [
				'repo'    => $this->getRepository(),
				'fields'  => array(),
				'primary' => array(),
				'unique'  => array(),
				'indexes' => array()
			];

			foreach ($this->getFields() as $field) {
				$config['fields'][$field] = array_merge([
					'data_name' => $this->getDataName($field),
					'type'      => $this->getType($field),
					'nullable'  => $this->getNullable($field),
					'default'   => $this->getDefault($field)
				], $this->getOptions($field));
			}

			$config['primary'] = $this->getPrimary();

			foreach ($this->getIndexes('unique') as $index) {
				$fields = $this->getUKey($index);

				if (count($fields) == 1) {
					$config['fields'][$fields[0]]['unique'] = TRUE;
				} else {
					$config['unique'][$index] = $fields;
				}
			}

			foreach ($this->getIndexes() as $index) {
				$config['indexes'][$index] = $this->getIndex($index);
			}

			return $config;
		}


		/**
		 *
		 */
		public function getDataName($field)
		{
			return isset(self::$dataNames[$this->class][$field])
				? self::$dataNames[$this->class][$field]
				: self::makeDataName($field);
		}


		/**
		 *
		 */
		public function getDefault($field)
		{
			return isset(self::$defaults[$this->class][$field])
				? self::$defaults[$this->class][$field]
				: NULL;
		}


		/**
		 *
		 */
		public function getDefaults()
		{
			return array_keys(self::$defaults[$this->class]);
		}


		/**
		 *
		 */
		public function getField($data_name)
		{
			return array_search($data_name, self::$dataNames[$this->class]);
		}


		/**
		 *
		 */
		public function getFields()
		{
			return self::$fields[$this->class];
		}


		/**
		 *
		 */
		public function getFieldMap($field)
		{
			$map = isset(self::$options[$this->class][$field])
				? self::$options[$this->class][$field]
				: array();

			switch ($type = $this->getType($field)) {
				case 'serial':
					$type = 'integer';
					break;
				case 'uuid':
					$type = 'string';
					break;
			}

			$map['type']       = $type;
			$map['fieldName']  = $field;
			$map['columnName'] = $this->getDataName($field);
			$map['nullable']   = $this->getNullable($field);

			return $map;
		}


		/**
		 *
		 */
		public function getIndex($index)
		{
			return isset(self::$indexes[$this->class][$index])
				? self::$indexes[$this->class][$index]
				: array();
		}


		/**
		 *
		 */
		public function getIndexes($type = NULL)
		{
			$type = strtolower($type);

			if (!$type) {
				return array_keys(self::$indexes[$this->class]);

			} elseif ($type == 'unique') {
				return array_keys(self::$ukeys[$this->class]);

			} else {
				return array();
			}
		}


		/**
		 *
		 */
		public function getNullable($field)
		{
			return in_array($field, self::$nullable[$this->class]);
		}


		/**
		 *
		 */
		public function getOptions($field)
		{
			return isset(self::$options[$this->class][$field])
				? self::$options[$this->class][$field]
				: array();
		}


		/**
		 *
		 */
		public function getPrimary()
		{
			return self::$primaries[$this->class];
		}


		/**
		 *
		 */
		public function getRepository()
		{
			return self::$repositories[$this->class];
		}


		/**
		 *
		 */
		public function getType($field)
		{
			return isset(self::$types[$this->class][$field])
				? self::$types[$this->class][$field]
				: self::DEFAULT_FIELD_TYPE;
		}


		/**
		 *
		 */
		public function getUKey($index)
		{
			return isset(self::$ukeys[$this->class][$index])
				? self::$ukeys[$this->class][$index]
				: array();
		}


		/**
		 *
		 */
		private function parse($config)
		{
			self::$dataNames[$this->class] = array();
			self::$nullable[$this->class]   = array();
			self::$options[$this->class]    = array();
			self::$indexes[$this->class]    = array();
			self::$types[$this->class]      = array();
			self::$ukeys[$this->class]      = array();

			$this->parseRepository($config);
			$this->parseFields($config);
			$this->parseTypes($config);

			$this->parseUKeys($config);
			$this->parsePrimary($config);
			$this->parseIndexes($config);

			$this->parseOptions($config);
			$this->parseDefaults($config);
		}


		/**
		 *
		 */
		private function parseDefaults($config)
		{
			foreach ($this->getFields() as $field) {
				if (isset($config['fields'][$field]['default'])) {
					self::$defaults[$this->class][$field] = $config['fields'][$field]['default'];
				}
			}
		}


		/**
		 *
		 */
		private function parseFields($config)
		{
			self::$fields[$this->class] = isset($config['fields'])
				? array_keys($config['fields'])
				: array();

			$data_names = array();

			foreach (self::$fields[$this->class] as $field) {
				$data_names[$field] = isset($config['fields'][$field]['data_name'])
					? $config['fields'][$field]['data_name']
					: self::makeDataName($field);
			}

			self::$dataNames[$this->class] = $data_names;
		}


		/**
		 *
		 */
		private function parseIndexes($config)
		{
			if (isset($config['indexes'])) {
				foreach ($config['indexes'] as $index => $fields) {
					settype($fields, 'array');

					if (is_numeric($index)) {
						$index = NULL;

						foreach ($fields as $field) {
							$index .= $this->getDataName($field) . '_';
						}

						$index .= 'idx';
					}

					self::$indexes[$this->class][$index] = $fields;
				}
			}
		}


		/**
		 *
		 */
		private function parseNullable($config)
		{
			foreach ($this->getFields() as $field) {
				if (isset($config['fields'][$field]['nullable'])) {
					if ((bool) $config['fields'][$field]['nullable']) {
						self::$nullable[$this->class][] = $field;
					}
				}
			}
		}


		/**
		 *
		 */
		private function parseOptions($config)
		{
			foreach ($this->getFields() as $field) {
				$options = array();

				switch ($this->getType($field)) {
					case 'string':
						if (isset($config['fields'][$field]['length'])) {
							$options['length'] = $config['fields'][$field]['length'];
						}
						break;
				}

				self::$options[$this->class][$field] = $options;
			}
		}


		/**
		 *
		 */
		private function parsePrimary($config)
		{
			if (isset($config['primary'])) {
				self::$primaries[$this->class] = !is_array($config['primary'])
					? [(string) $config['primary']]
					: $config['primary'];

			} else {
				//
				// Find the best candidate for a primary key
				//
			}
		}


		/**
		 *
		 */
		private function parseRepository($config)
		{
			self::$repositories[$this->class] = !isset($config['repo'])
				? self::makeRepositoryName($this->shortName)
				: $config['repo'];
		}


		/**
		 *
		 */
		private function parseTypes($config)
		{
			foreach (self::$fields[$this->class] as $field) {
				if (isset($config['fields'][$field]['type'])) {
					self::$types[$this->class][$field] = $config['fields'][$field]['type'];
				}
			}
		}


		/**
		 *
		 */
		private function parseUKeys($config)
		{
			foreach (self::$fields[$this->class] as $field) {
				if (!empty($config['fields'][$field]['unique'])) {
					$data_name  = $this->getDataName($field);
					$unique_idx = 'u_' . $data_name . '_idx';

				} else {
					continue;
				}

				self::$ukeys[$this->class][$unique_idx] = [$data_name];
			}

			if (isset($config['unique'])) {
				foreach ($config['unique'] as $unique_idx => $fields) {
					$key = array_map(function($field) {
						return $this->getDataName($field);
					}, $fields);

					if (is_numeric($unique_idx)) {
						$unique_idx = 'u_' . implode('_', $key) . '_idx';
					}

					self::$ukeys[$this->class][$unique_idx] = $key;
				}
			}
		}
	}
}
