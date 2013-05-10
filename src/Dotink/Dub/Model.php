<?php namespace Dotink\Dub
{
	use Dotink\Flourish;
	use Doctrine\ORM\Events;
	use Doctrine\ORM\UnitOfWork;
	use Doctrine\ORM\EntityManager;
	use Doctrine\ORM\Mapping\ClassMetadata;
	use Doctrine\ORM\Event\LifecycleEventArgs;
	use Doctrine\ORM\Mapping\Builder\ClassMetadataBuilder;

	/**
	 *
	 */
	abstract class Model
	{
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
		static private $relationshipTypes = [
			ClassMetadata::ONE_TO_ONE,
			ClassMetadata::ONE_TO_MANY,
			ClassMetadata::MANY_TO_ONE,
			ClassMetadata::MANY_TO_MANY
		];


		/**
		 *
		 */
		protected $validationMessages = array();


		/**
		 *
		 */
		final static public function configure($class, Array $config)
		{
			self::$configs[$class] = $config;
		}


		/**
		 *
		 */
		final static public function dynamicLoader($class)
		{
			if (isset(self::$configs[$class])) {
				$class_parts = explode('\\', $class);
				$short_name  = array_pop($class_parts);
				$namespace   = implode('\\', $class_parts);
				$fields      = self::fetchFields($class);

				ob_start();

				call_user_func(function($parent) use ($namespace, $short_name, $fields) {
					?>
					namespace <?= $namespace ?>
					{
						class <?= $short_name ?> extends \<?= $parent ?>
						{
							<?php foreach($fields as $field) { ?>
								protected $<?= $field ?> = NULL;
							<?php } ?>
						}
					}
					<?php
				}, __CLASS__);

				eval(ob_get_clean());
			}
		}


		/**
		 *
		 */
		final static public function loadMetadata(ClassMetadata $metadata)
		{
			$class = get_called_class();

			if ($class == __CLASS__) {
				$builder = new ClassMetadataBuilder($metadata);
				$builder->setMappedSuperclass();
				$builder->addLifecycleEvent('temper',   Events::prePersist);
				$builder->addLifecycleEvent('validate', Events::prePersist);
				$builder->addLifecycleEvent('validate', Events::preUpdate);
				return;
			}

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
					}

					$metadata->mapField($field_map);
				}
			}

			if (is_callable([$class, 'configureMetadata'])) {
				call_user_func([$class, 'configureMetadata'], new ClassMetadataBuilder($metadata));
			}
		}


		/**
		 *
		 */
		static private function fetchFields($class)
		{
			if (!isset(self::$fields[$class])) {
				$configured_fields = isset(self::$configs[$class]['fields'])
					? array_keys(self::$configs[$class]['fields'])
					: array();

				if (class_exists($class, FALSE)) {
					$concrete_fields = array_diff(
						array_keys(get_class_vars($class)),
						array_keys(get_class_vars(__CLASS__))
					);
				} else {
					$concrete_fields = array();
				}

				self::$fields[$class] = array_unique(array_merge(
					$configured_fields,
					$concrete_fields
				));
			}

			return self::$fields[$class];
		}


		/**
		 *
		 */
		static private function fetchFieldMaps($class)
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
		 static private function fetchRepositoryName($class)
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
		static private function addValidationMessage($entity, $field, $message)
		{
			if (!isset($entity->validationMessages[$field])) {
				$entity->validationMessages[$field] = array();
			}

			$entity->validationMessages[$field][] = $message;
		}


		/**
		 *
		 */
		static private function validateIsNotNull($entity, $field)
		{
			if ($entity->$field === NULL) {
				self::addValidationMessage($entity, $field, Flourish\Text::create(
					'Cannot be left empty'
				)->compose(NULL));
			}
		}


		/**
		 *
		 */
		static private function validateStringLength($entity, $field, $length)
		{
			if (Flourish\UTF8::len($entity->$field) > $length) {
				self::addValidationMessage($entity, $field, Flourish\Text::create(
					'Cannot exceed %s characters'
				)->compose(NULL, 30));
			}
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


		/**
		 *
		 */
		public function fetchValidationMessages()
		{
			return $this->validationMessages;
		}


		/**
		 *
		 */
		public function isDetached(EntityManager $em)
		{
			return $em->getUnitOfWork()->getEntityState($this) == UnitOfWork::STATE_DETACHED;
		}


		/**
		 *
		 */
		public function isManaged(EntityManager $em)
		{
			return $em->getUnitOfWork()->getEntityState($this) == UnitOfWork::STATE_MANAGED;
		}


		/**
		 *
		 */
		public function isNew(EntityManager $em)
		{
			return $em->getUnitOfWork()->getEntityState($this) == UnitOfWork::STATE_NEW;
		}


		/**
		 *
		 */
		public function isRemoved(EntityManager $em)
		{
			return $em->getUnitOfWork()->getEntityState($this) == UnitOfWork::STATE_REMOVED;
		}


		/**
		 *
		 */
		final public function populate($data)
		{
			$class = get_class($this);

			foreach (self::fetchFields($class) as $field) {
				$data_name = Flourish\Text::create($field)
					-> underscorize()
					-> compose(NULL)
				;

				if (isset($data[$data_name])) {
					$method = 'set' . Flourish\Text::create($field)
						-> camelize(TRUE)
						-> compose(NULL)
					;

					$this->$method($data[$data_name]);
				}
			}
		}


		/**
		 *
		 */
		final public function remove(EntityManager $em)
		{
			$class = get_class($this);
			$state = $em->getUnitOfWork()->getEntityState($this);

			if ($state == UnitOfWork::STATE_MANAGED) {
				$em->remove($this);
			}
		}


		/**
		 *
		 */
		final public function store(EntityManager $em = NULL)
		{
			$class = get_class($this);
			$state = $em->getUnitOfWork()->getEntityState($this);

			if (in_array($state, [UnitOfWork::STATE_NEW, UnitOfWork::STATE_REMOVED])) {
				$em->persist($this);
			}
		}


		/**
		 *
		 */
		final public function temper()
		{
			$class = get_class($this);

			if (isset(self::$configs[$class]['defaults'])) {
				foreach (self::$configs[$class]['defaults'] as $field => $default) {
					if ($this->$field !== NULL) {
						continue;
					}

					if (isset(self::$configs[$class]['fields'][$field]['type'])) {
						switch (self::$configs[$class]['fields'][$field]['type']) {
							case 'date':
							case 'datetime':
							case 'time':
							case 'object':
								if (class_exists($default)) {
									$this->$field = new $default();
								} else {
									$this->$field = $default;
								}
								break;
							default:
								$this->$field = $default;
						}
					}
				}
			}
		}


		/**
		 *
		 */
		final public function validate(LifeCycleEventArgs $e = NULL)
		{
			$class   = get_class($this);
			$manager = isset($e)
				? $e->getEntityManager()
				: NULL;

			if (!isset(self::$configs[$class]['fields'])) {
				return;
			}

			$fields_config = self::$configs[$class]['fields'];

			foreach ($fields_config as $field => $config) {
				if (isset($config['nullable']) && !$config['nullable']) {
					self::validateIsNotNull($this, $field);
				}

				if (!isset($config['type']) || $config['type'] == 'string') {
					if (isset($config['length'])) {
						self::validateStringLength($this, $field, $config['length']);
					}
				}
			}

			if (count($this->validationMessages)) {
				throw new ValidationException(sprintf(
					'Could not validate entity of type %s',
					$class
				));
			}
		}
	}
}
