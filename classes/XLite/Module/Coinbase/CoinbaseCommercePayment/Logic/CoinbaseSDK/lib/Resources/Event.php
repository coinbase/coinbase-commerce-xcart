<?php
namespace CoinbaseSDK\Resources;

use CoinbaseSDK\Operations\ReadMethodTrait;

class Event extends ApiResource
{
    use ReadMethodTrait;

    /**
     * @return string
     */
    public static function getResourcePath()
    {
        return 'events';
    }
}
