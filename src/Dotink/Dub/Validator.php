<?php namespace Dotink\Dub
{
	use Dotink\Flourish;

	class Validator
	{

	}
}
		/**
		 *
		 */
		protected $validationMessages = array();


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
		public function fetchValidationMessages()
		{
			return $this->validationMessages;
		}


		/**
		 *
		 */
		public function validate(LifeCycleEventArgs $e = NULL)
		{
			$config  = ModelConfiguration::load(get_class($this), __FUNCTION__);
			$manager = isset($e)
				? $e->getEntityManager()
				: NULL;

			$this->clearValidationMessages();

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
					get_class($this)
				));
			}
		}


		/**
		 *
		 */
		protected function clearValidationMessages()
		{
			$this->validationMessages = array();
		}


