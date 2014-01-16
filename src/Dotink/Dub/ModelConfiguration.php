<?php namespace Dotink\Dub
{
	use Dotink\Flourish;
	use Doctrine\ORM\EntityManager;
	use Doctrine\ORM\Mapping\ClassMetadata;


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
		static private $dataNames = array();


		/**
		 *
		 */
		static private $defaults = array();


		/**
		 *
		 */
		static private $fields = array();


		/**
		 *
		 */
		static private $indexes = array();


		/**
		 *
		 */
		static private $localAssociationMaps = array();


		/**
		 *
		 */
		static private $nullable = array();


		/**
		 *
		 */
		static private $options = array();


		/**
		 *
		 */
		static private $pivotAssociationMaps = array();


		/**
		 *
		 */
		static private $pivotRepositories = array();


		/**
		 *
		 */
		static private $primaries = array();


		/**
		 *
		 */
		static private $references = array();


		/**
		 *
		 */
		static private $relationships = array();


		/**
		 *
		 */
		static private $repositories = array();


		/**
		 *
		 */
		static private $types = array();


		/**
		 *
		 */
		static private $ukeys = array();


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
		static public function listConfiguredClasses()
		{
			return array_keys(self::$configs);
		}


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
		static public function makeReferenceEntity($repo)
		{
			return strpos($repo, '_') !== FALSE
				? Flourish\Text::create($repo)->camelize(TRUE)->singularize()->compose()
				: ucfirst(Flourish\Text::create($repo)->singularize()->compose());
		}


		/**
		 *
		 */
		static public function makeReferenceField($repo, $plural = FALSE)
		{
			$entity = self::makeReferenceEntity($repo);

			return $plural
				? lcfirst(Flourish\Text::create($entity)->pluralize()->compose())
				: lcfirst($entity);
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
            return self::$configs[$class] = new self($class, $config);
        }


        /**
         *
         */
        static public function reflect($class, EntityManager $database, $repo = NULL)
        {
			$connection  = $database->getConnection();
			$schema      = $connection->getSchemaManager();
			$ref_columns = array();

			if (!$repo) {
				$class_parts = explode('\\', $class);
				$short_name  = array_pop($class_parts);
				$repo        = self::makeRepositoryName($short_name);
			}

			$columns = $schema->listTableColumns($repo);
			$indexes = $schema->listTableIndexes($repo);
			$fkeys   = $schema->listTableForeignKeys($repo);

			$config  = [
				'repo'    => $repo,
				'fields'  => array(),
				'primary' => array(),
				'unique'  => array(),
				'indexes' => array()
			];

			foreach ($schema->listTables() as $table) {
				foreach ($schema->listTableIndexes($table->getName()) as $index) {
					if ($index->isPrimary() || $index->isUnique()) {
						$pivot_columns   = $index->getColumns();
						$fkey_references = array();

						if (count($pivot_columns) != 2) {
							continue;
						}

						foreach ($schema->listTableForeignKeys($table->getName()) as $fkey) {
							$fkey_columns = $fkey->getColumns();

							if (count($fkey_columns) != 1) {
								continue;
							}

							if ($fkey->getForeignTableName() == $repo) {
								$fkey_references['local'] = $fkey;
							} elseif(in_array($fkey_columns[0], $pivot_columns)) {
								$fkey_references['pivot'] = $fkey;
							}
						}

						if (count($fkey_references) == 2) {
							$ref_table = $fkey_references['pivot']->getForeignTableName();
							$field     = self::makeReferenceField($ref_table, TRUE);

							$config['fields'][$field] = [
								'references' => self::makeReferenceEntity($ref_table),
								'via'        => [
									'repo'   => $table->getName(),
									'local'  => array_combine(
										$fkey_references['local']->getLocalColumns(),
										$fkey_references['local']->getForeignColumns()
									),
									'pivot'  => array_combine(
										$fkey_references['pivot']->getLocalColumns(),
										$fkey_references['pivot']->getForeignColumns()
									)
								]
							];
						}
					}
				}
			}

			foreach ($fkeys as $fkey) {
				$local_columns   = $fkey->getLocalColumns();
				$foreign_columns = $fkey->getForeignColumns();
				$foreign_table   = $fkey->getForeignTableName();

				$ref_columns = array_merge($ref_columns, $local_columns);
				$ref_field   = [
					'references' => self::makeReferenceEntity($foreign_table),
					'via'        => array()
				];

				foreach ($schema->listTableIndexes($foreign_table) as $index) {
					if (!array_diff($index->getColumns(), $foreign_columns)) {
						if ($index->isPrimary() || $index->isUnique()) {
							$ref_field['unique'] = TRUE;
						}
					}
				}

				foreach ($indexes as $index) {
					if (!array_diff($index->getColumns(), $fkey->getLocalColumns())) {
						if ($index->isPrimary() || $index->isUnique()) {
							$ref_field['via']['unique'] = TRUE;
						}
					}
				}

				$ref_field['via']['local'] = array();

				foreach ($local_columns as $i => $column) {
					$ref_field['via']['local'][$column] = $foreign_columns[$i];
				}

				$field = !$ref_field['unique']
					? self::makeReferenceField($foreign_table, TRUE)
					: self::makeReferenceField($foreign_table);

				$config['fields'][$field] = $ref_field;
			}

			foreach ($columns as $column) {
				$name = $column->getName();

				if (in_array($name, $ref_columns)) {
					continue;
				}

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

				switch (strtolower($config['fields'][$field]['type'])) {
					case 'bigint':
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

				if ($nullable) {
					$config['fields'][$field]['nullable'] = TRUE;
				}

				//
				// Add defaults
				//

				if ($default !== NULL) {
					$config['fields'][$field]['default'] = $default;
				}

				switch ($config['fields'][$field]['type']) {
					case 'date':
					case 'time':
					case 'datetime':
						if ($default == 'now()' || $default == 'CURRENT_DATETIME') {
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

			return self::store($class, $config);
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

				if ($this->getType($field) == 'association') {
					$field_data = array_merge([
						'references' => $this->getReference($field),
						'via'        => [
							'local'  => $this->getLocalAssociationMap($field)
						]
					], $this->getOptions($field));

					switch ($this->getRelationship($field)) {
						case ClassMetadata::ONE_TO_ONE:
							$field_data['unique']        = TRUE;
							$field_data['via']['unique'] = TRUE;
							break;

						case ClassMetadata::MANY_TO_ONE:
							$field_data['via']['unique'] = TRUE;
							break;

						case ClassMetadata::ONE_TO_MANY:
							$field_data['unique'] = TRUE;
							break;

						case ClassMetadata::MANY_TO_MANY:
							$field_data['via']['repo']  = $this->getPivotRepository($field);
							$field_data['via']['pivot'] = $this->getPivotAssociationMap($field);
					}

					$config['fields'][$field] = $field_data;

				} else {
					$config['fields'][$field] = array_merge([
						'data_name' => $this->getDataName($field),
						'type'      => $this->getType($field),
						'nullable'  => $this->getNullable($field),
						'default'   => $this->getDefault($field)
					], $this->getOptions($field));
				}
			}

			$config['primary'] = $this->getPrimary();

			foreach ($this->getIndexes('unique') as $index) {
				$fields = $this->getUKey($index);

				if (count($fields) == 1 && isset($config['fields'][$fields[0]])) {
					$config['fields'][$fields[0]]['unique'] = TRUE;
				} else {
					$config['unique'][$index] = count($fields) == 1
						? $fields[0]
						: $fields;
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
		public function getAssociationMap($field)
		{
			$map['fieldName']    = $field;
			$map['targetEntity'] = $this->getReference($field);

			$local_map = array();

			foreach ($this->getLocalAssociationMap($field) as $data_name => $ref_name) {
				$local_map[] = ['name' => $data_name, 'referencedColumnName' => $ref_name];
			}

			if ($pivot_repo = $this->getPivotRepository($field)) {
				$pivot_map = array();

				foreach ($this->getPivotAssociationMap($field) as $data_name => $ref_name) {
					$pivot_map[] = ['name' => $data_name, 'referencedColumnName' => $ref_name];
				}

				$map['joinTable'] = [
					'name'                => $pivot_repo,
					'joinColumns'         => $local_map,
					'inverseJoinColumns'  => $pivot_map
				];

			} else {
				$map['joinColumns'] = $local_map;
			}

			return array_merge($map, $this->getOptions($field));
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
		public function getFields($type = NULL)
		{
			return isset($type)
				? array_keys(self::$types[$this->class], $type)
				: self::$fields[$this->class];
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
		public function getLocalAssociationMap($field)
		{
			return isset(self::$localAssociationMaps[$this->class][$field])
				? self::$localAssociationMaps[$this->class][$field]
				: NULL;
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
		public function getPivotAssociationMap($field)
		{
			return isset(self::$pivotAssociationMaps[$this->class][$field])
				? self::$pivotAssociationMaps[$this->class][$field]
				: NULL;
		}


		/**
		 *
		 */
		public function getPivotRepository($field)
		{
			return isset(self::$pivotRepositories[$this->class][$field])
				? self::$pivotRepositories[$this->class][$field]
				: NULL;
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
		public function getReference($field)
		{
			return isset(self::$references[$this->class][$field])
				? self::$references[$this->class][$field]
				: NULL;
		}


		/**
		 *
		 */
		public function getRelationship($field)
		{
			return isset(self::$relationships[$this->class][$field])
				? self::$relationships[$this->class][$field]
				: NULL;
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
				: NULL;
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
		private function buildRemoteAssociationMap($target_entity)
		{
			$target_config = self::load($target_entity);
			$target_pkey   = $target_config->getPrimary();

			if (count($target_pkey) != 1) {
				throw new Flourish\EnvironmentException(
					'Cannot autogenerate association map, remote pkey is compound'
				);
			}

			return [self::makeDataName($target_config->shortName) => reset($target_pkey)];
		}


		/**
		 *
		 */
		private function parse($config)
		{
			//
			// Initialize all array configurations for this class
			//

			self::$dataNames[$this->class]            = array();
			self::$defaults[$this->class]             = array();
			self::$indexes[$this->class]              = array();
			self::$localAssociationMaps[$this->class] = array();
			self::$nullable[$this->class]             = array();
			self::$options[$this->class]              = array();
			self::$pivotAssociationMaps[$this->class] = array();
			self::$pivotRepositories[$this->class]    = array();
			self::$references[$this->class]           = array();
			self::$relationships[$this->class]        = array();
			self::$types[$this->class]                = array();
			self::$ukeys[$this->class]                = array();


			$this->parseRepository($config);
			$this->parseFields($config);
			$this->parseTypes($config);
			$this->parseOptions($config);
			$this->parseDefaults($config);
			$this->parseNullable($config);

			$this->parseUKeys($config);
			$this->parsePrimary($config);
			$this->parseIndexes($config);

			$this->parseAssociations($config);
		}


		/**
		 *
		 */
		private function parseAssociations($config)
		{
			foreach (self::$references[$this->class] as $field => $target_entity) {
				$local_map = NULL;
				$has_many  = empty($config['fields'][$field]['unique']);
				$unique    = FALSE;
				$pivots    = FALSE;
				$pkey      = $this->getPrimary();

				if (isset($config['fields'][$field]['via'])) {
					$via_mapping = $config['fields'][$field]['via'];
					$unique      = !empty($via_mapping['unique']);
					$pivots      =  isset($via_mapping['repo']);

					if (isset($via_mapping['local'])) {
						$local_map = $via_mapping['local'];
					}
				}

				if (!isset($local_map)) {
					try {
						if ($pivots) {
							$local_map = [self::makeDataName($this->shortName) => reset($pkey)];

						} elseif (!$has_many) {
							$local_map = $this->buildRemoteAssociationMap($target_entity);

						} elseif (count($pkey) == 1) {
							$local_map = [reset($pkey) => self::makeDataName($this->shortName)];

						} else {
							throw new Flourish\EnvironmentException(
								'Cannot autogenerate association map, local pkey is compound'
							);
						}

					} catch (Flourish\EnvironmentException $e) {
						throw new Flourish\ProgrammerException(
							'Invalid association for %s, must define local map in `via`',
							$field
						);
					}
				}

				self::$localAssociationMaps[$this->class][$field] = $local_map;

				if ($unique) {
					$local_data_names = array_keys($local_map);

					if (count(array_diff($pkey, $local_data_names))) {
						$unique_idx = 'u_assoc_' . self::makeDataName($field) . '_idx';

						self::$ukeys[$this->class][$unique_idx] = $local_data_names;
					}

					self::$relationships[$this->class][$field] = $has_many
						? ClassMetadata::ONE_TO_MANY
						: ClassMetadata::ONE_TO_ONE;

				} else {
					self::$relationships[$this->class][$field] = $has_many
						? ClassMetadata::MANY_TO_MANY
						: ClassMetadata::MANY_TO_ONE;
				}

				if (!$pivots) {

					//
					// If the association doesn't pivot, then we are done
					//

					continue;
				}

				self::$pivotRepositories[$this->class][$field] = $via_mapping['repo'];

				if (isset($via_mapping['pivot'])) {
					$pivot_map = $via_mapping['pivot'];

				} else {
					try {
						$pivot_map = $this->buildRemoteAssociationMap($target_entity);

					} catch (Flourish\EnvironmentException $e) {
						throw new Flourish\ProgrammerException(
							'Invalid association for %s, must define pivot map in `via`',
							$field
						);
					}
				}

				self::$pivotAssociationMaps[$this->class][$field] = $pivot_map;
			}
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
			self::$fields[$this->class]    = array();
			self::$dataNames[$this->class] = array();

			if (isset($config['fields'])) {
				foreach ($config['fields'] as $field => $field_data) {
					self::$fields[$this->class][] = $field;

					if (isset($field_data['references'])) {
						self::$references[$this->class][$field] = $field_data['references'];
					} else {
						self::$dataNames[$this->class][$field] = isset($field_data['data_name'])
							? $field_data['data_name']
							: self::makeDataName($field);
					}
				}
			}
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
			foreach ($config['fields'] as $field => $field_data) {
				if (isset($field_data['nullable'])) {
					if ((bool) $field_data['nullable']) {
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

					case 'association':
						if (isset($config['fields'][$field]['on_delete'])) {
							$options['onDelete'] = $config['fields'][$field]['on_delete'];
						}

						if (isset($config['fields'][$field]['remove_orphans'])) {
							$options['orphanRemoval'] = $config['fields'][$field]['remove_orphans'];
						}

						if (isset($config['fields'][$field]['cascade'])) {
							$options['cascade'] = $config['fields'][$field]['on_delete'];
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

				self::$primaries[$this->class] = array();
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
				if (isset($config['fields'][$field]['references'])) {
					self::$types[$this->class][$field] = 'association';
				} elseif (isset($config['fields'][$field]['type'])) {
					self::$types[$this->class][$field] = $config['fields'][$field]['type'];
				} else {
					self::$types[$this->class][$field] = self::DEFAULT_FIELD_TYPE;
				}
			}
		}


		/**
		 *
		 */
		private function parseUKeys($config)
		{
			foreach (self::$fields[$this->class] as $field) {
				if ($this->getType($field) == 'association') {
					continue;
				}

				if (empty($config['fields'][$field]['unique'])) {
					continue;
				}

				$data_name  = $this->getDataName($field);
				$unique_idx = 'u_' . $data_name . '_idx';

				self::$ukeys[$this->class][$unique_idx] = [$data_name];
			}

			if (isset($config['unique'])) {
				foreach ($config['unique'] as $unique_idx => $fields) {
					settype($fields, 'array');

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
