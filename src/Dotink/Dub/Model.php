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
		static private $generatedTypes = [
			'serial',
			'uuid'
		];


		/**
		 *
		 */
		protected $validationMessages = array();

		/**
		 *
		 */
		final static public function create($class, $scaffold_only = FALSE)
		{
			if (class_exists($class)) {
				if (!is_subclass_of($class, __CLASS__)) {
					throw new Flourish\ProgrammerException(
						'Cannot create non-model class %s',
						$class
					);
				}

			} else {
				$config = ModelConfiguration::load($class, 'create model');

				ob_start();
				call_user_func(function($parent) use ($config) {
					?>
					namespace <?= $config->get('namespace') ?>
					{
						class <?= $config->get('shortName') ?> extends \<?= $parent ?>
						{
							<?php foreach($config->getFields() as $field) { ?>
								protected $<?= $field ?> = NULL;
							<?php } ?>
						}
					}
					<?php
				}, __CLASS__);

				eval(ob_get_clean());
			}

			return !$scaffold_only
				? new $class()
				: NULL;
		}


		/**
		 *
		 */
		final static public function loadMetadata(ClassMetadata $metadata)
		{
			$class   = get_called_class();
			$builder = new ClassMetadataBuilder($metadata);

			if ($class == __CLASS__) {
				$builder->setMappedSuperclass();
				$builder->addLifecycleEvent('temper',   Events::prePersist);
				$builder->addLifecycleEvent('validate', Events::prePersist);
				$builder->addLifecycleEvent('validate', Events::preUpdate);
				return;
			}

			$config = ModelConfiguration::load($class, 'load meta data');

			$metadata->setTableName($config->getRepository());
			$metadata->setIdentifier($config->getPrimary());

			foreach ($config->getFields() as $field) {
				$metadata->mapField($config->getFieldMap($field));

				switch ($config->getType($field)) {
					case 'serial':
						$metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_AUTO);
						break;
					case 'uuid':

						break;
				}
			}

			foreach ($config->getIndexes() as $index) {
				$builder->addIndex($config->getIndex($index), $index);
			}

			foreach ($config->getIndexes('unique') as $index) {
				$builder->addUniqueConstraint($config->getUKey($index), $index);
			}
/*
			foreach ($config->getReferences() as $reference) {
				switch ($config->getReferenceType($reference)) {
						case ClassMetadata::ONE_TO_ONE:
							$metadata->mapOneToOne($config->getReferenceMap($reference));
							break;

						case ClassMetadata::ONE_TO_MANY:
							$metadata->mapOneToMany($config->getReferenceMap($reference));
							break;

						case ClassMetadata::MANY_TO_ONE:
							$metadata->mapManyToOne($config->getReferenceMap($reference));
							break;

						case ClassMetadata::MANY_TO_MANY:
							$metadata->mapManyToMany($config->getReferenceMap($reference));
							break;
				}
			}
*/

			if (is_callable([$class, 'configureMetadata'])) {
				call_user_func([$class, 'configureMetadata'], $builder);
			}
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
			$config = ModelConfiguration::load(get_class($this), 'call magic method');
			$snaked = Flourish\Text::create($method)->underscorize()->compose();

			$parts    = explode('_', $snaked, 2);
			$action   = reset($parts);
			$property = lcfirst(substr($method, strlen($action)));

			if (!in_array($property, $config->getFields())) {
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
			$config = ModelConfiguration::load(get_class($this), __FUNCTION__);

			foreach ($data as $data_name => $value) {
				if ($field  = $config->getField($data_name)) {
					$method = 'set' . ucfirst($field);

					$this->$method($value);
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
			$config = ModelConfiguration::load(get_class($this), __FUNCTION__);

			foreach ($config->getDefaults() as $field) {
				if ($this->$field !== NULL) {
					continue;
				}

				$default_value = $config->getDefault($field);

				if (preg_match('#^\+(.*)\(\)$#', $default_value, $matches)) {
					$default_value = new $matches[1]();
				}

				$this->$field = $default_value;
			}
		}


		/**
		 *
		 */
		final public function validate(LifeCycleEventArgs $e = NULL)
		{
			$config  = ModelConfiguration::load(get_class($this), __FUNCTION__);
			$manager = isset($e)
				? $e->getEntityManager()
				: NULL;

			foreach ($config->getFields() as $field) {
				$type    = $config->getType($field);
				$options = $config->getOptions($field);

				if (in_array($type, self::$generatedTypes)) {

				} elseif (!$config->getNullable($field)) {
					self::validateIsNotNull($this, $field);
				}

				if ($type == 'string' && isset($options['length'])) {
					self::validateStringLength($this, $field, $options['length']);
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
