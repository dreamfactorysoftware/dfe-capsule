<?php namespace DreamFactory\Enterprise\Instance\Capsule\Patterns;

class DreamFactory extends BaseCapsulePattern
{
    //******************************************************************************
    //* Methods
    //******************************************************************************

    /** @inheritdoc */
    public static function getCapsulePattern()
    {
        //  The DreamFactory application pattern
        return [
            'Illuminate\Contracts\Http\Kernel'            => 'DreamFactory\Http\Kernel',
            'Illuminate\Contracts\Console\Kernel'         => 'DreamFactory\Console\Kernel',
            'Illuminate\Contracts\Debug\ExceptionHandler' => 'DreamFactory\Exceptions\Handler',
        ];
    }
}
