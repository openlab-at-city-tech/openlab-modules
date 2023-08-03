<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit9c5cebf499b5a26a5a035babc1323be2
{
    public static $prefixLengthsPsr4 = array (
        'O' => 
        array (
            'OpenLab\\Modules\\' => 16,
        ),
        'H' => 
        array (
            'HardG\\CptTax\\' => 13,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'OpenLab\\Modules\\' => 
        array (
            0 => __DIR__ . '/../..' . '/classes',
        ),
        'HardG\\CptTax\\' => 
        array (
            0 => __DIR__ . '/..' . '/hard-g/cpt-tax/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit9c5cebf499b5a26a5a035babc1323be2::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit9c5cebf499b5a26a5a035babc1323be2::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit9c5cebf499b5a26a5a035babc1323be2::$classMap;

        }, null, ClassLoader::class);
    }
}
