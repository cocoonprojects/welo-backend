<?php

namespace Test;

class TestFixturesHelper
{

    public static function generateRandomName() {
        return round(microtime(true) * 1000).'_'.rand(0,10000);
    }

    public static function generateRandomEmail() {
        return round(microtime(true) * 1000).'_'.rand(0,10000).'@foo.com';
    }

}