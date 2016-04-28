<?php namespace Dotink\Dub
{
	use Dotink\Flourish;
	use Doctrine\ORM\Events;
	use Doctrine\ORM\UnitOfWork;
	use Doctrine\ORM\EntityManager;
	use Doctrine\ORM\Mapping\ClassMetadata;
	use Doctrine\ORM\Event\LifecycleEventArgs;
	use Doctrine\Common\Collections\ArrayCollection;
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
			'association',
			'serial',
			'uuid',
		];


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
		static public function loadMetadata(ClassMetadata $metadata)
		{
			$class   = get_called_class();
			$builder = new ClassMetadataBuilder($metadata);

			if ($class == __CLASS__) {
				$builder->setMappedSuperclass();
				$builder->addLifecycleEvent('temper',   Events::prePersist);
				return;
			}

			$config = ModelConfiguration::load($class, 'load meta data');

			$metadata->setTableName($config->getRepository());
			$metadata->setIdentifier($config->getPrimary());

			foreach ($config->getFields() as $field) {
				if ($config->getType($field) == 'association') {
					continue;
				}

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

			foreach ($config->getFields('association') as $field) {
				switch ($config->getRelationship($field)) {
						case ClassMetadata::ONE_TO_ONE:
							$metadata->mapOneToOne($config->getAssociationMap($field));
							break;

						case ClassMetadata::ONE_TO_MANY:
							$metadata->mapOneToMany($config->getAssociationMap($field));
							break;

						case ClassMetadata::MANY_TO_ONE:
							$metadata->mapManyToOne($config->getAssociationMap($field));
							break;

						case ClassMetadata::MANY_TO_MANY:
							$metadata->mapManyToMany($config->getAssociationMap($field));
							break;
				}
			}

			if (is_callable([$class, 'configureMetadata'])) {
				call_user_func([$class, 'configureMetadata'], $builder);
			}
		}


		/**
		 *
		 */
		public function __construct()
		{
			$config = ModelConfiguration::load(get_class($this));

			foreach ($config->getFields('association') as $field) {

				if (!class_exists($related_class = $config->getReference($field))) {
					throw new Flourish\ProgrammerException(
						'Cannot instantiate model of type %s, related class %s does not exist',
						get_class($this),
						$related_class
					);
				}

				$relationship  = $config->getRelationship($field);
				$to_many_types = [
					ClassMetadata::ONE_TO_MANY,
					ClassMetadata::MANY_TO_MANY
				];

				if (in_array($relationship, $to_many_types)) {
					$this->$field = new ArrayCollection();
				}
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
		public function isDetached(EntityManager $em = NULL)
		{
			return $em
				? $em->getUnitOfWork()->getEntityState($this) == UnitOfWork::STATE_DETACHED
				: FALSE;
		}


		/**
		 *
		 */
		public function isManaged(EntityManager $em = NULL)
		{
			return $em
				? $em->getUnitOfWork()->getEntityState($this) == UnitOfWork::STATE_MANAGED
				: FALSE;
		}


		/**
		 *
		 */
		public function isNew(EntityManager $em = NULL)
		{
			return $em
				? $em->getUnitOfWork()->getEntityState($this) == UnitOfWork::STATE_NEW
				: TRUE;
		}


		/**
		 *
		 */
		public function isRemoved(EntityManager $em = NULL)
		{
			return $em
				? $em->getUnitOfWork()->getEntityState($this) == UnitOfWork::STATE_REMOVED
				: FALSE;
		}


		/**
		 *
		 */
		public function populate($data)
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
		public function remove(EntityManager $em = NULL)
		{
			if (!$em) {
				return;
			}

			$state = $em->getUnitOfWork()->getEntityState($this);

			if ($state == UnitOfWork::STATE_MANAGED) {
				$em->remove($this);
			}
		}


		/**
		 *
		 */
		public function store(EntityManager $em = NULL)
		{
			if (!$em) {
				return;
			}

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
	}
}
