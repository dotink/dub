<?php namespace Dotink\Dub\Type
{
	use Doctrine\DBAL\Types\Type;
	use Doctrine\DBAL\Platforms\AbstractPlatform;

	class TSVectorType extends Type
	{
		const TSVECTOR = 'tsvector';

		public function canRequireSQLConversion()
		{
			return true;
		}


		public function convertToDatabaseValue($value, AbstractPlatform $platform)
		{
			if (is_array($value)) {
				$value = implode(" ", $value);
			}

			return $value;
		}


		public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform)
		{
			return sprintf('to_tsvector(%s)', $sqlExpr);
		}


		public function convertToPHPValue($value, AbstractPlatform $platform)
		{
			return $value;
		}


		public function getName()
		{
			return self::TSVECTOR;
		}


		public function getSqlDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
		{
			return "TSVECTOR";
		}
	}
}
