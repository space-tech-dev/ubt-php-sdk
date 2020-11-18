<?php


namespace SpaceCycle;


class Utils
{
    static function isNoEnvProduction() {
        return
            env('APP_ENV') === 'dev' ||
            env('APP_ENV') === 'development' ||
            env('APP_ENV') === 'test' ||
            env('APP_ENV') === 'staging';
    }
}