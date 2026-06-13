<?php
/**
 * OLT Driver Factory
 */
class OLT_Factory
{
    public static function getDriver($type)
    {
        $className = $type;
        $file = __DIR__ . '/' . $className . '.php';

        if (file_exists($file)) {
            require_once $file;
            if (class_exists($className)) {
                return new $className();
            }
        }

        // Fallback to Mock for testing
        require_once __DIR__ . '/Mock_Driver.php';
        return new Mock_Driver();
    }
}
