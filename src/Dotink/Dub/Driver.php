<?php namespace Dotink\Dub
{
    use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
    use Doctrine\Common\Persistence\Mapping\ClassMetadata;

    /**
     * A custom driver for Dub
     *
     * This driver is super simple and is configuration driven.
     */
    class Driver implements MappingDriver
    {
        /**
         * Paths of entity directories.
         *
         * @access private
         * @var array
         */
        private $paths = array();


        /**
         * Map of all class names.
         *
         * @access private
         * @var array
         */
        private $classNames;


        /**
         * Get all registered entity classes
         *
         * @access private
         * @return array The registered entity classes
         */
        public function getAllClassNames()
        {
            return array_keys(self::$configs);
        }


        /**
         * Determines if a class is transient (non-entity)
         *
         * @access public
         * @param string $class_name The class to check
         * @return boolean TRUE if the class is transient, FALSE otherwise
         */
        public function isTransient($class_name)
        {
            return !method_exists($class_name, 'loadMetadata');
        }


        /**
         * Loads metadata for a given class
         *
         * @access public
         * @param string $className The class to load metdata for
         * @param ClassMetadata $metadata The metdata object
         * @return void
         */
        public function loadMetadataForClass($class_name, ClassMetadata $metadata)
        {
            $class_name::loadMetadata($metadata);
        }
    }
}
