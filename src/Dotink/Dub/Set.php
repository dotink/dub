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
	abstract class Set
	{
		const ALIAS_NAME = 'data';

		static public function build(EntityManager $em, $search = NULL, $order = NULL, $limit = NULL, $page = NULL)
		{
			$records = new static();

			if ($search) {
				$records->where((array) $search);
			}


			$counter = clone $builder;

			if ($limit) {
				$builder->setMaxResults($limit);
				$builder->setFirstResult(($page - 1) * $limit);
			}

			$builder->add('select', new Expr\Select(['data']));
			$counter->add('select', new Expr\Select(['COUNT(data)']));

			$data  = $builder->getQuery()->getResult();
			$count = $counter->getQuery()->getSingleScalarResult();

		}

		public function __construct()
		{
			$this->set     = get_class($this);
			$this->model   = Flourish\Text::create($this->set)->singularize()->compose();
			$this->builder = $em->createQueryBuilder();

			$this->builder->add('from', new Expr\From($records->model, self::ALIAS_NAME));
		}


		[
			'status:=' => 'Active',
			[
				'datePublished:<' => $today,

			],
			[
				'datePublished:='  => $today,
				'timePublished:>=' => $now
			]
		]


		/**
		 *
		 *
		 */
		public function define(Array $terms = [])
		{
			$this->builder->setParameters([]);
			$this->builder->where($this->expandTerms($terms);
		}


		private function expandTerms($terms, $pcount = 0)
		{
			$and  = $this->builder->expr()->andx();
			$ors  = $this->builder->expr()->orx();
			$expr = $and->add($ors);

			foreach ($terms as $field = $value) {

				$numeric_key = is_numeric($field);
				$operation   = '=';

				if (!$numeric_key && preg_match_all('/^([^\:]*)\:([^\:]+)$/', $field, $matches)) {
					$operation = $matches[2][0];
				}




				if (is_array($value) && !in_array($operation, ['=', '!'])) {


				}

					}
					if ($operation = )
					if (is_numeric($field)) {
						$ors->add($this->expandTerms($value, $pcount));
					}
					foreach ($value as $or_value) {
						$this->addComparison($ors, $field, $operation, $or_value, ++$pcount);
					}

					continue;
				}

				if (preg_match_all('/^([^\:]*)\:([^\:]+)$/', $field, $matches)) {
					$operation = $matches[2][0];

					if (is_array($value)) {
						foreach ($value as $or_value) {
							$this->addComparison($ors, $field, $operation, $or_value, ++$pcount));
						}

						continue;
					}

				} elseif (is_numeric($field)) {
					if (is_array($value)) {
						$ors->add($this->expandTerms($value, $pcount));
						continue;
					}

					$operation = '!';
					$value     = NULL;

				} elseif (is_array($value)) {
					$operation = 'in';
				}

				$and->add($this->makeComparison($field, $operation, $value, ++$pcount)
			}

			return $expr;
		}

		private function makeComparison($field, $operator, $value, $pcount) {
			$method_translations = [
				'='  => 'eq',
				'<'  => 'lt',
				'>'  => 'gt',
				'~'  => 'like',
				'!'  => 'neq',
				'<=' => 'lte',
				'>=' => 'gte',
				'in' => 'in'
			];

			if (!isset($method_translations[$operator])) {
				throw new Flourish\ProgrammerException(
					'Invalid operator %s specified', $operator
				);
			}

			$method = $method_translations[$operator];

			return $this->builder->expr()->$method($field, '?' .

		}

	}