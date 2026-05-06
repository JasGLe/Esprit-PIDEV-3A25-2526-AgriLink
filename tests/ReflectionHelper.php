<?php

namespace App\Tests;

use ReflectionClass;

/**
 * Classe utilitaire pour accéder aux propriétés/méthodes protégées dans les tests
 */
class ReflectionHelper
{
    /**
     * Définit une propriété protégée/privée
     * 
     * @param object $object
     * @param string $propertyName
     * @param mixed $value
     */
    public static function setProperty(object $object, string $propertyName, mixed $value): void
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * Récupère une propriété protégée/privée
     * 
     * @param object $object
     * @param string $propertyName
     * @return mixed
     */
    public static function getProperty(object $object, string $propertyName): mixed
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Appelle une méthode protégée/privée
     * 
     * @param object $object
     * @param string $methodName
     * @param array $arguments
     * @return mixed
     */
    public static function callMethod(object $object, string $methodName, array $arguments = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $arguments);
    }
}
