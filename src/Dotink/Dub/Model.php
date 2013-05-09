<?php namespace Dotink\Dub
{
	use Dotink\Flourish;
	use Doctrine\ORM\Mapping\ClassMetadata;

	/**
	 *
	 */
	abstract class Model
	{
		static private $configs = array();

		static private $fields = array();

		static private $relationshipTypes = [
			ClassMetadata::ONE_TO_ONE,
			ClassMetadata::ONE_TO_MANY,
			ClassMetadata::MANY_TO_ONE,
			ClassMetadata::MANY_TO_MANY
		];



		/**
		 *
		 */
		final public static function configure($class, Array $config)
		{
			self::$configs[$class] = $config;
		}


		/**
		 *
		 */
		final public static function loadMetadata(ClassMetadata $metadata)
		{
			$class = get_called_class();

			$metadata->setTableName(self::fetchRepositoryName($class));

			foreach (self::fetchFieldMaps($class) as $field_map) {
				if (in_array($field_map['type'], self::$relationshipTypes)) {
					$type = $field_map['type'];

					unset($field_map['type']);

					switch ($type) {
						case ClassMetadata::ONE_TO_ONE:
							$metadata->mapOneToOne($field_map);
							break;

						case ClassMetadata::ONE_TO_MANY:
							$metadata->mapOneToMany($field_map);
							break;

						case ClassMetadata::MANY_TO_ONE:
							$metadata->mapManyToOne($field_map);
							break;

						case ClassMetadata::MANY_TO_MANY:
							$metadata->mapManyToMany($field_map);
							break;
					}

				} else {

					if ($field_map['type'] == 'serial') {
						$field_map['type'] = 'integer';

						$metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_AUTO);

						/*
					     *     'allocationSize' => 20,
					     *     'initialValue'   => 1
					     *     'quoted'         => 1
						 */
					}

					$metadata->mapField($field_map);
				}
			}

			if (is_callable([$class, 'configureMetadata'])) {
				call_user_func([$class, 'configureMetadata'], $metadata);
			}
		}


		/**
		 *
		 */
		private static function fetchFields($class)
		{
			if (!isset(self::$fields[$class])) {
				self::$fields[$class] = array_diff(
					array_keys(get_class_vars($class)),
					array_keys(get_class_vars(__CLASS__))
				);
			}

			return self::$fields[$class];
		}


		/**
		 *
		 */
		private static function fetchFieldMaps($class)
		{
			$field_maps = array();

			foreach (self::fetchFields($class) as $field) {
				$field_map = isset(self::$configs[$class]['fields'][$field])
					? self::$configs[$class]['fields'][$field]
					: array();

				if (!isset($field_map['fieldName'])) {
					$field_map['fieldName'] = $field;
				}

				if (!isset($field_map['type'])) {
					$field_map['type'] = 'string';
				}

				if (!in_array($field_map['type'], self::$relationshipTypes)) {

					if (!isset($field_map['columnName'])) {
						$field_map['columnName'] = Flourish\Text::create($field)
							-> underscorize()
							-> compose()
						;
					}

					if (isset(self::$configs[$class]['pkey'])) {
						settype(self::$configs[$class]['pkey'], 'array');

						if (in_array($field_map['fieldName'], self::$configs[$class]['pkey'])) {
							$field_map['id'] = true;
						}
					}
				}

				$field_maps[] = $field_map;
			}

			return $field_maps;
		}


		/**
		 *
		 */
		private static function fetchRepositoryName($class)
		{
			if (!isset(self::$configs[$class]['repo'])) {
				$class_parts = explode('\\', $class);
				$class_name  = end($class_parts);
				$repo_name   = Flourish\Text::create($class_name)
					-> underscorize()
					-> pluralize()
					-> compose();
			} else {
				$repo_name = self::$configs[$class]['repo'];
			}

			return $repo_name;
		}


		/**
		 *
		 */
		public function __call($method, $args)
		{
			$class    = get_class($this);
			$parts    = explode('_', Flourish\Text::create($method)->underscorize()->compose(), 2);
			$action   = reset($parts);
			$property = lcfirst(substr($method, strlen($action)));

			if (!in_array($property, self::fetchFields($class))) {
				throw new Flourish\ProgrammerException(
					'Method %s() called on unknown property %s',
					$method,
					$property
				);
			}

			switch ($action) {
				case 'get':
					return $this->$property ;
					break;

				case 'set':
					if (isset($args[0])) {
						$this->$property = $args[0];
					} else {
						$this->$property = NULL;
					}

					break;

				default:
					throw new Flourish\ProgrammerException(
						'Method %s() called with unknown action %s',
						$method,
						$action
					);
					break;
			}
		}
	}
}
