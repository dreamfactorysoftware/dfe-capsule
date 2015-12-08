<?php namespace DreamFactory\Enterprise\Instance\Capsule\Contracts;

interface ProvidesCapsulePattern
{
    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * Return an array of application components used to bootstrap a laravel application
     *
     * @return array The application components
     */
    public static function getCapsulePattern();
}
