```php

<?php namespace Dotink\Dub
{
	use Doctrine\ORM\Mapping\ClassMetadata;

	Model::config('Vendor\Project\Model', [
		'table'  => 'table_name',
		'pkey'	 => ['id'],
		'fields' => [
			'id'            => ['type' => 'integer'],
			'username'      => ['type' => 'string'],
			'emailAddresses => [
				'type'         => ClassMetdata::ONE_TO_MANY,
				'targetEntity' => 'EmailAddress',
				'mappedBy'     => 'user',
				'inversedBy'   => 'id'
			]
		]
	]);
}
```